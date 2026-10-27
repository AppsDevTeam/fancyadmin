<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat;

use ADT\AiChat\Mcp\McpResponse;
use ADT\AiChat\Mcp\McpServer;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\AiMcpToken;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;
use DateTimeImmutable;
use Nette\Http\IRequest;

/**
 * MCP endpoint AI asistenta: ověří Bearer token (AiMcpToken), práva jeho vlastníka
 * a požadavek předá McpServeru z adt/ai-chat.
 *
 * Bez session a bez přihlášení přes SecurityUser - práva se čtou rovnou z rolí
 * identity (Identity::isAllowed()), takže požadavek nepotřebuje cookie ani CSRF.
 * Platí stejná pravidla jako pro přihlášení do portálu (aktivní identita,
 * FancyAdmin::canSignIn()) a pro AI asistenta v portálu (AiPresenterTrait).
 */
class AiMcpEndpoint
{
	/** Jak často nejvýš se zapisuje lastUsedAt - ať každý dotaz klienta nepíše do DB. */
	private const LAST_USED_RESOLUTION_SECONDS = 60;

	public function __construct(
		private readonly McpServer $server,
		private readonly EntityManager $em,
		private readonly FancyAdmin $fancyAdmin,
		private readonly string $tokenClass,
	) {
	}

	public function handle(IRequest $request): McpResponse
	{
		$method = $request->getMethod();
		if (!in_array($method, ['POST', 'DELETE'], true)) {
			// GET (SSE stream) server nenabízí; odpověď nic neprozradí, ověření netřeba
			return McpResponse::methodNotAllowed();
		}

		$rawToken = $this->parseBearerToken($request->getHeader('Authorization'));
		if ($rawToken === null) {
			return McpResponse::unauthorized('Missing bearer token');
		}

		$token = $this->findToken($rawToken);
		if ($token === null || $token->isExpired()) {
			return McpResponse::unauthorized('Invalid or expired token');
		}

		$identity = $token->getIdentity();
		if (!$identity->getIsActive() || !$this->fancyAdmin->canSignIn($identity)) {
			return McpResponse::unauthorized('The token owner cannot sign in');
		}

		if (!$this->isAllowed($identity)) {
			return McpResponse::forbidden('The token owner is not allowed to use the MCP server');
		}

		$this->touch($token);

		return $this->server->handle($method, (string) $request->getRawBody(), [
			'identityId' => $identity->getId(),
			'mcpTokenId' => $token->getId(),
		]);
	}

	/**
	 * Stejně jako AiPresenterTrait::checkAiTenantAccess(): AI čte celé anonymizované
	 * schéma, účty nerozlišuje - kde jich v jedné databázi žije víc, smí jen ten, kdo
	 * smí vidět data všech účtů.
	 */
	protected function isAllowed(Identity $identity): bool
	{
		if (!$identity->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_MCP)) {
			return false;
		}

		return !$this->fancyAdmin->isMultiTenant() || $identity->isAllowed($this->fancyAdmin->getFullDataAclResource());
	}

	private function parseBearerToken(?string $header): ?string
	{
		if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
			return null;
		}

		return $m[1];
	}

	/**
	 * Přímo přes repozitář, ne přes query objekt: ten filtruje podle přihlášeného
	 * uživatele (security a account filtr), a ten tu žádný není.
	 */
	private function findToken(string $rawToken): ?AiMcpToken
	{
		$token = $this->em->getRepository($this->tokenClass)->findOneBy(['tokenHash' => ApiKeyHasher::hash($rawToken)]);

		return $token instanceof AiMcpToken ? $token : null;
	}

	private function touch(AiMcpToken $token): void
	{
		$now = new DateTimeImmutable();
		$lastUsed = $token->getLastUsedAt();
		if ($lastUsed !== null && $now->getTimestamp() - $lastUsed->getTimestamp() < self::LAST_USED_RESOLUTION_SECONDS) {
			return;
		}

		$token->setLastUsedAt($now);
		$this->em->flush();
	}
}
