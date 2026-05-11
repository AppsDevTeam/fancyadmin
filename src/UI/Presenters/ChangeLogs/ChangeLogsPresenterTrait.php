<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\ChangeLogs;

use ADT\FancyAdmin\UI\Components\Grids\ChangeLog\ChangeLogGrid;
use ADT\FancyAdmin\UI\Components\Grids\ChangeLog\ChangeLogGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;

trait ChangeLogsPresenterTrait
{
	use PresenterTrait;

	public function actionDefault(): void
	{
		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function createComponentChangeLogGrid(ChangeLogGridFactory $factory): ChangeLogGrid
	{
		return $factory->create();
	}
}
