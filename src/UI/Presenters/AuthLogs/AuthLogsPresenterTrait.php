<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\AuthLogs;

use ADT\FancyAdmin\UI\Components\Grids\AuthLog\AuthLogGrid;
use ADT\FancyAdmin\UI\Components\Grids\AuthLog\AuthLogGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;

trait AuthLogsPresenterTrait
{
	use PresenterTrait;

	public function actionDefault(): void
	{
		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function createComponentAuthLogGrid(AuthLogGridFactory $factory): AuthLogGrid
	{
		return $factory->create();
	}
}
