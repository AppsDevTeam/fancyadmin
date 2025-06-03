<?php

namespace ADT\FancyAdmin\UI\Presenters;

use Nette\Application\UI\Renderable;
use Nette\Http\IResponse;
use stdClass;

interface BasePresenter extends Renderable
{
	public function error(string $message = '', int $httpCode = IResponse::S404_NotFound): void;
	public function redirect(string $destination, $args = []): void;


	public function flashMessageError(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageWarning(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null): stdClass;
	public function flashMessageInfo(string $message, ?int $autoCloseDuration = null): stdClass;
}
