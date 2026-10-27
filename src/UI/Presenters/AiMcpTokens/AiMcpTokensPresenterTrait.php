<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\AiMcpTokens;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\AiMcpTokenFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\AiMcpTokenQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\AiMcpToken;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Grids\AiMcpToken\AiMcpTokenGrid;
use ADT\FancyAdmin\UI\Components\Grids\AiMcpToken\AiMcpTokenGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

/**
 * Správa vlastních MCP tokenů AI asistenta (připojení Claude Code, Claude Desktop, ...).
 * Endpoint, na který se tokeny používají, je AiMcpPresenterTrait. Zapojení viz README
 * (AI asistent, MCP server).
 */
trait AiMcpTokensPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use AiMcpTokenFormFactoryInject;
	use AiMcpTokenQueryFactoryInject;

	protected ?AiMcpToken $aiMcpToken = null;

	/** Absolutní URL MCP endpointu, např. $this->link('//:Api:Mcp:default'). */
	abstract protected function getAiMcpEndpointUrl(): string;

	public function actionDefault(?AiMcpToken $aiMcpToken = null): void
	{
		$this->checkAiMcpAccess();

		if ($aiMcpToken) {
			$this->assertOwnAiMcpToken($aiMcpToken);
			$this->aiMcpToken = $aiMcpToken;
		}

		$this->template->aiMcpEndpointUrl = $this->getAiMcpEndpointUrl();
		$this->template->aiMcpServerName = $this->getAiMcpClientServerName();
		$this->setPackageTemplateFile(__DIR__ . '/default.latte');
	}

	public function handleEdit(AiMcpToken $aiMcpToken): void
	{
		$this->checkAiMcpAccess();
		$this->assertOwnAiMcpToken($aiMcpToken);
		$this->aiMcpToken = $aiMcpToken;
		$this->redrawSidePanel();
	}

	public function handleNew(): void
	{
		$this->checkAiMcpAccess();
		$this->aiMcpToken = null;
		$this->redrawSidePanel();
	}

	public function createComponentAiMcpTokenGrid(AiMcpTokenGridFactory $factory): AiMcpTokenGrid
	{
		return $factory->create();
	}

	/**
	 * Stejná pravidla jako endpoint (AiMcpEndpoint): právo BACKOFFICE_AI_MCP, a kde žije
	 * v jedné databázi víc účtů, i právo na data všech účtů. Token, se kterým by se
	 * stejně nedalo nic dělat, nemá smysl vydávat.
	 */
	protected function checkAiMcpAccess(): void
	{
		if (!$this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_MCP)
			|| ($this->_securityUser->isMultiTenant() && !$this->_securityUser->isAllowedFullDataAclResource())
		) {
			$this->error('', 403);
		}
	}

	/** Jméno serveru v ukázkovém příkazu `claude mcp add`. */
	protected function getAiMcpClientServerName(): string
	{
		return $this->_fancyAdmin->getProject();
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_aiMcpTokenFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_aiMcpTokenQueryFactory->create()->byIdentity($this->_securityUser->getId());
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->aiMcpToken;
	}

	private function assertOwnAiMcpToken(AiMcpToken $token): void
	{
		if ($token->getIdentity()->getId() !== $this->_securityUser->getId()) {
			$this->error();
		}
	}
}
