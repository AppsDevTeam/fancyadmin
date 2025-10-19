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

	protected function getTemplateFile(): ?string
	{
		$reflectionClass = new \ReflectionClass($this);
		$templateName = $reflectionClass->getShortName() .'.latte';

		$templateFile = dirname($reflectionClass->getFileName()) . '/' . $templateName;
		if (file_exists($templateFile)) {
			return $templateFile;
		}

		foreach ($reflectionClass->getInterfaces() as $_interface => $_interfaceReflectionClass) {
			if (str_contains($_interface, $reflectionClass->getShortName())) {
				$templateFile = dirname($_interfaceReflectionClass->getFileName()) . '/' . $templateName;
				if (file_exists($templateFile)) {
					return $templateFile;
				}
			}
		}

		return null;
	}
}