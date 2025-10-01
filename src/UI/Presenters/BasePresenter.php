<?php

namespace ADT\FancyAdmin\UI\Presenters;

use Nette\Application\UI\Renderable;
use stdClass;

interface BasePresenter extends Renderable
{
	const int DEFAULT_AUTO_CLOSE_DURATION = 3000;

	public function flashMessageError(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageWarning(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageInfo(string $message, ?int $autoCloseDuration = null): stdClass;
}
