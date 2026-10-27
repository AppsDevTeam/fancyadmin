<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\AiMcpToken;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DeleteParams;
use ADT\FancyAdmin\Model\Entities\AiMcpToken;
use ADT\FancyAdmin\Model\Queries\AiMcpTokenQuery;
use ADT\FancyAdmin\Model\Queries\Factories\AiMcpTokenQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;

/**
 * MCP tokeny přihlášeného uživatele. Smazání token okamžitě zneplatní.
 */
trait AiMcpTokenGridTrait
{
	use Editable;
	use SearchFilter;

	/** Kolik znaků otisku tokenu se zobrazuje v gridu. */
	protected int $tokenFingerprintLength = 8;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;

		$this->addSearchFilter($grid, ['name']);

		$grid->addColumnText('name', 'fcadmin.presenters.aiMcpTokens.grid.name');

		// samotný token zobrazit nelze (uložený je jen otisk), pro rozlišení stačí jeho začátek
		$grid->addColumnText('tokenHash', 'fcadmin.presenters.aiMcpTokens.grid.fingerprint')
			->setRenderer(fn(AiMcpToken $token) => $token->getTokenHash()
				? mb_substr($token->getTokenHash(), 0, $this->tokenFingerprintLength) . '…'
				: '');

		$grid->addColumnDateTime('createdAt', 'fcadmin.presenters.aiMcpTokens.grid.createdAt')
			->setFormat('j. n. Y H:i')
			->setSortable();

		$grid->addColumnText('expiresAt', 'fcadmin.presenters.aiMcpTokens.grid.expiresAt')
			->setRenderer(fn(AiMcpToken $token) => match (true) {
				$token->getExpiresAt() === null => $this->getTranslator()->translate('fcadmin.presenters.aiMcpTokens.grid.neverExpires'),
				$token->isExpired() => $this->getTranslator()->translate('fcadmin.presenters.aiMcpTokens.grid.expired'),
				default => $token->getExpiresAt()->format('j. n. Y'),
			});

		$grid->addColumnDateTime('lastUsedAt', 'fcadmin.presenters.aiMcpTokens.grid.lastUsedAt')
			->setFormat('j. n. Y H:i')
			->setSortable();
	}

	/** @param AiMcpTokenQuery $queryObject */
	protected function initQueryObject($queryObject): void
	{
		// i administrátor vidí jen svoje tokeny - token nese práva svého vlastníka
		$queryObject->byIdentity($this->_securityUser->getId());
	}

	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams();
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AiMcpTokenQueryFactory::class;
	}
}
