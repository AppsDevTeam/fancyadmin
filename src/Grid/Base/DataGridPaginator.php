<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Grid\Base;

use Nette;

/**
 * @property-read Nette\Application\UI\ITemplate $template
 */
class DataGridPaginator extends \Ublaboo\DataGrid\Components\DataGridPaginator\DataGridPaginator
{
	public function getTemplateFile(): string
	{
		return __DIR__ . '/DataGridPaginator.latte';
	}
}
