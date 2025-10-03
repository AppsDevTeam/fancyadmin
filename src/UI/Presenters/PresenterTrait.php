<?php

namespace ADT\FancyAdmin\UI\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;
use Nette\Http\IResponse;
use Nette\Security\User;
use stdClass;

trait PresenterTrait
{
	abstract public function error(string $message = '', int $httpCode = IResponse::S404_NotFound): void;
	abstract public function getAction(): string;
	abstract public function getParameter(string $name): mixed;
	abstract public function getPresenter(): ?Presenter;
	abstract public function getSnippetId(string $name): string;
	abstract public function getTemplate(): ?Template;
	abstract public function getUser(): User;
	abstract public function isControlInvalid(): bool;
	abstract public function redirect(string $destination, $args = []): void;
	abstract public function redrawControl(string $name): void;

	abstract public function flashMessageError(string $message, ?int $autoCloseDuration = null): stdClass;
	abstract public function flashMessageWarning(string $message, ?int $autoCloseDuration = null): stdClass;
	abstract public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null): stdClass;
	abstract public function flashMessageInfo(string $message, ?int $autoCloseDuration = null): stdClass;
}