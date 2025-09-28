<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use Nette\Application\UI\Presenter;

trait FormTrait
{
	abstract public function getPresenter(): ?Presenter;
}