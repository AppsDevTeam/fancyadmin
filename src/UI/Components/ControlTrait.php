<?php

namespace ADT\FancyAdmin\UI\Components;

use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;

trait ControlTrait
{
	abstract public function getParameter(string $name): mixed;
	abstract public function getPresenter(): ?Presenter;
	abstract public function getSnippetId(string $name): string;
	abstract public function getTemplate(): ?Template;
}