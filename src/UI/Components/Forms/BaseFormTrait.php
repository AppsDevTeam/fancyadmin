<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\Forms\BootstrapFormRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Localization\Translator;

trait BaseFormTrait
{
	use FormTrait;

	abstract protected function getEntityManager(): EntityManagerInterface;
	abstract protected function getTranslator(): Translator;
	abstract protected function getIdentity(): Identity;

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

	protected function getTemplateFilename(): ?string
	{
		$templateName = new \ReflectionClass($this)->getShortName() .'.latte';
		$dirname = dirname(new \ReflectionClass($this)->getFileName());
		$templateFile = $dirname . '/' . $templateName;
		if (!file_exists($templateFile)) {
			$templateFile = __DIR__ . '/' . $templateName;
		}
		return $templateFile;
	}
}
