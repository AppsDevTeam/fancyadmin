<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms\SignIn;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Administration;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\Forms\Form;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;
use Nette\Utils\ArrayHash;
use ADT\FancyAdmin\Exception\AuthenticationProcessException;

trait SignInFormTrait
{
	abstract public function getAuthenticator(): DoctrineAuthenticator;
	abstract public function getContext(): ?string;
	abstract public function getPresenter(): ?Presenter;

	#[Autowire]
	protected Administration $administration;

	protected Identity $identity;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addText('email')
			->setHtmlAttribute('id', 'login-form-input-email')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.email')
			->setRequired('fcadmin.forms.signIn.errors.emailRequired');

		$form->addPassword('password')
			->setHtmlAttribute('id', 'login-form-input-password')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.password')
			->setRequired('fcadmin.forms.signIn.errors.passwordRequired');

		$form->addSubmit('submit', 'fcadmin.forms.signIn.labels.logIn')
			->getControlPrototype()->class[] = 'w-100';

		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(ArrayHash $values, Form $form): void
	{
		try {
			$this->identity = $this->getAuthenticator()->authenticate($values->email, $values->password, $this->getContext());
		} catch (AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	/**
	 * @throws AuthenticationException
	 * @throws InvalidLinkException
	 */
	public function processForm(): never
	{
		$this->getPresenter()->user->login($this->identity);

		if ($selectedCompany = $this->getPresenter()->user->getIdentity()->getFilteredCompany()?->getId()) {
			$this->getPresenter()->redirect('Home:default', ['do' => 'redrawBody', 'selectedCompany' => $selectedCompany]);
		} else {
			$this->getPresenter()->redirect('Dashboard:default', ['do' => 'redrawBody']);
		}
	}

	public function render(): void
	{
		$this->template->administration = $this->administration;
		parent::render();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	protected function getTemplateFilename(): ?string
	{
		return __DIR__ . '/SignInForm.latte';
	}
}
