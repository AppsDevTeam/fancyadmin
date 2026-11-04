<?php

namespace ADT\FancyAdmin\UI\Components\Grids;

/**
 * Malý grid v panelu: hledání a šipky stránkování v liště nad tabulkou, bez čísel stránek
 * a výběru počtu řádků (docs/datagrid-compact.md). Patří do potomka ADT\Datagrid\Component\DataGrid.
 */
trait CompactDataGridTrait
{
	/** @var array{string, string}|null */
	private ?array $compactPagerTitles = null;

	public function render(): void
	{
		$this->addClasses('datagrid-compact');
		parent::render();
	}

	public function getTemplateFile(): string
	{
		return $this->templateFile ?? __DIR__ . '/DataGridCompact.latte';
	}

	/** Počet řádků je jen jeden, ten z setDefaultPerPage() */
	public function getItemsPerPageList(): array
	{
		$perPage = $this->defaultPerPage ?? 5;

		return [$perPage => $perPage];
	}

	/** Popisky šipek, třeba „Novější“ a „Starší“ u gridu řazeného od nejnovějšího */
	public function setCompactPagerTitles(string $previous, string $next): static
	{
		$this->compactPagerTitles = [$previous, $next];

		return $this;
	}

	/** @return array{string, string} */
	public function getCompactPagerTitles(): array
	{
		return $this->compactPagerTitles ?? [
			$this->getTranslator()->translate('ublaboo_datagrid.previous'),
			$this->getTranslator()->translate('ublaboo_datagrid.next'),
		];
	}
}
