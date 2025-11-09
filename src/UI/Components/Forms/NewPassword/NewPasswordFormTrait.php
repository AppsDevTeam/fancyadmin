<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Security\AuthenticationException;
use Nette\Utils\ArrayHash;

trait NewPasswordFormTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
	use OnetimeTokenQueryFactoryInject;
	use SecurityUserInject;
	use EntityManagerInject;

	protected Identity $identity;

	public function __construct(Identity $identity)
	{
		parent::__construct();
		$this->identity = $identity;
	}

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addSection(function () use ($form) {
//			$form->addText('firstName')
//				->setHtmlAttribute('placeholder', 'Jméno') // TODO translate
//				->setRequired();
//
//			$form->addText('lastName')
//				->setHtmlAttribute('placeholder', 'Příjmení') // TODO translate
//				->setRequired();
//
//			$form->addEmail('email')
//				->setHtmlAttribute('placeholder', 'E-mail') // TODO translate
//				->setRequired();
//
//			$form->addPhoneNumber('phoneNumber', null, 'Zadejte validní telefonní číslo') // TODO trnaslate
//				->setHtmlAttribute('placeholder', 'Telefon') // TODO translate
//				->setRequired();

			$form->addPassword('password')
				->setHtmlAttribute('placeholder', 'app.forms.newPassword.labels.password') // TODO translate
				->setRequired('app.forms.newPassword.errors.required');

			$form->addPassword('passwordRepeat')
				->setHtmlAttribute('placeholder', 'app.forms.newPassword.labels.passwordAgain') // TODO translate
				->setRequired('app.forms.newPassword.errors.required');
		}, 'inputsWrap');

		$form->addSubmit('submit', 'Uložit'); // TODO translate
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(array $values, Form $form): void
	{
		if ($values['password'] !== $values['passwordRepeat']) {
			$form->getComponentTextInput('passwordRepeat')->addError('app.forms.newPassword.errors.noMatch'); // TODO
		}
	}

	public function processForm(array $values): void
	{
		$this->_securityUser->logout(true);

		$this->_securityUser->login($this->identity, context: $this->_fancyAdmin->getContext());

		$this->_securityUser->getIdentity()->setPassword($values['password']);
		$this->_em->flush();

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Identity::class);
	}
}
