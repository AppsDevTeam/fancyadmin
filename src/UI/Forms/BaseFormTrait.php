<?php

namespace ADT\FancyAdmin\UI\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\Forms\BootstrapFormRenderer;
use Doctrine\ORM\EntityManagerInterface;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelSize;
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

	protected function redirectAfterLogin()
	{
		if ($selectedAccount = $this->getIdentity()->getSelectecAccount()) {
			$this->getPresenter()->redirect('Home:default', ['do' => 'redrawBody', 'selectedCompany' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect('Dashboard:default', ['do' => 'redrawBody']);
		}
	}
}
