<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;

trait FormTrait
{
	abstract public function getTemplate(): Template;
	abstract public function getPresenter(): ?Presenter;
}