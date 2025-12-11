<?php

namespace ADT\FancyAdmin\UI\Components\Forms\LostPassword;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\Forms\Form;

trait LostPasswordFormTrait
{
	use ControlTrait;
	use IdentityQueryFactoryInject;
	use MailerInject;
	use AuthenticatorInject;
	use FancyAdminInject;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addEmail('email')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.lostPassword.labels.email')
			->setRequired('fcadmin.forms.lostPassword.errors.emailRequired');

		$form->addSubmit('submit', 'fcadmin.forms.lostPassword.labels.submit');
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
	}

	public function processForm(array $values): never
	{
		/** @var Identity $identity */
		if (!$identity = $this->_authenticator->findIdentity($values['email'], $this->_fancyAdmin->getContext())) {
			$this->getPresenter()->flashMessageError('fcadmin.forms.lostPassword.messages.error');
			$this->getPresenter()->redirect('this');
		}

		$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);
		$this->processFormRedirect();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	protected function processFormRedirect(): never
	{
		$this->getPresenter()->flashMessageSuccess('fcadmin.forms.lostPassword.messages.success');
		$this->getPresenter()->redirect('lostPasswordSuccess');
	}
}
