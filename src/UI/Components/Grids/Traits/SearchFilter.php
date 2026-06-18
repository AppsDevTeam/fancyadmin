<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait SearchFilter
{
	/** @var string[]
	 *
	 * Dodatečná pole pro fulltextový filtr "search"; lze nastavit třemi způsoby:
	 *
	 * A) Override initGrid v komponentě (nejexplicitnější):
	 * use ProfileGridTrait {
	 *  ProfileGridTrait::initGrid as traitInitGrid;
	 * }
	 *
	 * public function initGrid(DataGrid $grid): void
	 * {
	 *  $this->searchFields = ['identity.username'];
	 *  $this->traitInitGrid($grid);
	 * }
	 *
	 * B) Přes setter při vytváření komponenty (před
	 * připojením k presenteru):
	 * protected function createComponentProfileGrid(): ProfileGrid
	 * {
	 *  $grid = $this->profileGridFactory->create();
	 *  $grid->setSearchFields(['identity.username']);
	 *  return $grid;
	 * }
	 *
	 * C) V konstruktoru komponenty (jen zaregistruje monitor, initGrid ještě neběží):
	 * public function __construct()
	 * {
	 *  parent::__construct();
	 *  $this->searchFields = ['identity.username'];
	 * }
	 *
	 */
	protected array $searchFields = [];

	/**
	 * Přidá fulltextový filtr "search" sloučený z výchozích polí gridu a případných polí nastavených přes setSearchFields().
	 *
	 * @param string[] $defaultSearchFields výchozí pole, která prohledává daný grid
	 */
	protected function addSearchFilter(DataGrid $grid, array $defaultSearchFields = []): void
	{
		$grid->addFilterText('search', '', array_unique(array_merge($defaultSearchFields, $this->searchFields)));
	}

	/**
	 * @param string[] $searchFields
	 */
	public function setSearchFields(array $searchFields): static
	{
		$this->searchFields = $searchFields;
		return $this;
	}
}
