<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms\SignIn;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Administration;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\Forms\Form;
use ADT\FancyAdmin\UI\Forms\BaseForm;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Utils\ArrayHash;

trait SignInFormTrait
{
	abstract public function getAuthenticator(): DoctrineAuthenticator;
	abstract public function getContext(): ?string;

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

	public function validateForm(ArrayHash $values): void
	{
		try {
			$this->identity = $this->authenticator->authenticate($values->email, $values->password, $this->getContext());
		} catch (AuthenticationException $e) {
			$this->form->addError('fcadmin.forms.signIn.errors.wrongEmailOrPassword');
		} catch (AuthenticationUserNotActiveException $e) {
			$this->form->addError('fcadmin.forms.signIn.errors.suspendedAccount');
		}
	}

	/**
	 * @throws AuthenticationException
	 * @throws InvalidLinkException
	 */
	public function processForm(): void
	{
		$this->presenter->user->login($this->identity);
		$this->presenter->redirect('Home:default', ['do' => 'redrawBody']);
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
