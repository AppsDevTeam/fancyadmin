<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\Forms\BootstrapFormRenderer;

trait BaseFormTrait
{
	use FormTrait;
	use EntityManagerInject;
	use TranslatorInject;

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->_translator);
		$form->setEntityManager($this->_em);
		$form->setRenderer(new BootstrapFormRenderer($form));
		return $form;
	}

	public function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}

	public function getRedirect($entity = null): ?array
	{
		return null;
	}

	protected function getTemplateFilename(): ?string
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
