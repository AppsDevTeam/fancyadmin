<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat;

use ADT\AiChat\Mcp\McpTokenPolicy;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;

/**
 * Pravidla pro vlastníka MCP tokenu (adt/ai-chat McpTokenAuthenticator): stejná jako pro
 * přihlášení do portálu (aktivní identita, FancyAdmin::canSignIn()) a pro AI asistenta
 * (resource BACKOFFICE_AI_MCP, v multi-tenant DB jen kdo vidí data všech účtů).
 *
 * Požadavek běží bez přihlášení - práva se čtou rovnou z rolí identity. Projekt, jehož
 * nástroje potřebují přihlášeného uživatele, službu přepíše a v runAs() ho přihlásí
 * bezstavově (jen do paměti).
 */
class AiMcpTokenPolicy implements McpTokenPolicy
{
	public function __construct(
		private readonly FancyAdmin $fancyAdmin,
	) {
	}

	public function canSignIn(mixed $owner): bool
	{
		return $owner instanceof Identity && $owner->getIsActive() && $this->fancyAdmin->canSignIn($owner);
	}

	/**
	 * Stejně jako AiPresenterTrait::checkAiTenantAccess(): AI čte celé anonymizované
	 * schéma, účty nerozlišuje.
	 */
	public function isAllowed(mixed $owner): bool
	{
		if (!$owner instanceof Identity || !$owner->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_MCP)) {
			return false;
		}

		return !$this->fancyAdmin->isMultiTenant() || $owner->isAllowed($this->fancyAdmin->getFullDataAclResource());
	}

	public function runAs(mixed $owner, callable $handle): mixed
	{
		return $handle();
	}
}
