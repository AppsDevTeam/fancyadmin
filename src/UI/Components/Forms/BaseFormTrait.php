<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\Forms\BootstrapFormRenderer;

trait BaseFormTrait
{
	use ControlTrait;
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
}
