<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Defaults;

use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\GridFilter;
use ADT\FancyAdmin\UI\Components\Grids\BaseGridTrait;
use Nette\Application\UI\Presenter;

/**
 * Základ výchozích gridů balíčku, které DI rozšíření zaregistruje, když projekt vlastní grid
 * (továrnu) nemá. Závislosti dostane přes inject metody BaseGridTrait.
 */
abstract class DefaultBaseGrid extends \ADT\Datagrid\Component\BaseGrid
{
	use BaseGridTrait;

	public ?GridFilter $entity = null;

	/**
	 * Hledaný výraz z adresy (odkaz z quickbaru, vyhledávání v hlavičce) jako filtr gridu.
	 */
	public function initGrid(DataGrid $grid): void
	{
		if (!empty($grid->filter['search'])) {
			return;
		}
		/** @var Presenter $presenter */
		$presenter = $this->getPresenter();
		$httpRequest = $presenter->getHttpRequest();
		$search = $httpRequest->getQuery('search')
			?? $httpRequest->getPost('search')
			?? ($httpRequest->getQuery('filter')['search'] ?? null)
			?? ($httpRequest->getPost('filter')['search'] ?? null);
		if ($search) {
			$grid->filter['search'] = $search;
		}
	}

	public function getDataGridClass(): string
	{
		return DataGrid::class;
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}
}
