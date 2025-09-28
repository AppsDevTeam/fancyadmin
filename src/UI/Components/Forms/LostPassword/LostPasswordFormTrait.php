<?php

namespace ADT\FancyAdmin\UI\Components\Forms\LostPassword;

use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\Forms\Form;
use Nette\Utils\ArrayHash;

trait LostPasswordFormTrait
{
	use FormTrait;
	use IdentityQueryFactoryInject;
	use MailerInject;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addEmail('email')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.lostPassword.labels.email')
			->setRequired('fcadmin.forms.lostPassword.errors.emailRequired');

		$form->addSubmit('submit', 'fcadmin.forms.lostPassword.labels.submit');
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function processForm(ArrayHash $values): never
	{
		/** @var Identity $identity */
		if (!$identity = $this->_identityQueryFactory->create()->byUsername($values['email'])->fetchOneOrNull()) {
			$this->getPresenter()->flashMessageError('fcadmin.forms.lostPassword.messages.error');
			$this->getPresenter()->redirect('this');
		}

		$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);
		$this->getPresenter()->flashMessageSuccess('fcadmin.forms.lostPassword.messages.success');
		$this->getPresenter()->redirect('this');
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
