<?php

namespace ADT\FancyAdmin\UI\Forms;

use ADT\DoctrineForms\Form;
use ADT\Forms\BootstrapFormRenderer;
use Doctrine\ORM\EntityManagerInterface;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelSize;
use Nette\Localization\Translator;

trait BaseFormTrait
{
	abstract protected function getEntityManager(): EntityManagerInterface;
	abstract protected function getTranslator(): Translator;

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->getTranslator());
		$form->setEntityManager($this->getEntityManager());
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
