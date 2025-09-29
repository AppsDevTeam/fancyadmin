<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\SignIn;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;
use Nette\Utils\ArrayHash;

trait SignInFormTrait
{
	use FormTrait;
	use RedirectAfterLoginTrait;
	use FancyAdminInject;
	use AuthenticatorInject;

	private Identity $_identity;

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

		$this->getTemplate()->isLostPasswordEnabled = $this->_fancyAdmin->isLostPasswordEnabled();
	}

	public function validateForm(array $values, Form $form): void
	{
		try {
			$this->_identity = $this->_authenticator->authenticate($values['email'], $values['password'], $this->_fancyAdmin->getLoginContext()->value);
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
		$this->getPresenter()->user->login($this->_identity);

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
