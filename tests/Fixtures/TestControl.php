<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\RenderToStringTrait;
use Nette\Application\UI\Control;

/** Komponenta s traitami balicku - sablona lezi vedle tridy fixtures. */
class TestControl extends Control
{
	use ControlTrait;
	use RenderToStringTrait;

	public function render(): void
	{
		echo 'obsah komponenty';
	}

	public function callGetTemplateFile(): ?string
	{
		return $this->getTemplateFile();
	}
}

/** Komponenta, ke ktere zadna sablona neexistuje. */
final class TestControlWithoutTemplate extends TestControl
{
}

/** Sablonu nema u sebe, ale u rozhrani, jehoz nazev obsahuje nazev tridy. */
final class PanelControl extends TestControl implements Panel\PanelControlFactory
{
}
