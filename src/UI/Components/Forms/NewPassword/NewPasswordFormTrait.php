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
	use SecurityUserInject;
	use EntityManagerInject;

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
				->setHtmlAttribute('placeholder', 'fcadmin.forms.newPassword.labels.password') // TODO translate
				->setRequired('fcadmin.forms.newPassword.errors.required');

			$form->addPassword('passwordRepeat')
				->setHtmlAttribute('placeholder', 'fcadmin.forms.newPassword.labels.passwordAgain') // TODO translate
				->setRequired('fcadmin.forms.newPassword.errors.required');
		}, 'inputsWrap');

		$form->addSubmit('submit', 'Uložit'); // TODO translate
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(array $values, Form $form): void
	{
		if ($values['password'] !== $values['passwordRepeat']) {
			$form->getComponentTextInput('passwordRepeat')->addError('fcadmin.forms.newPassword.errors.noMatch'); // TODO
		}
	}

	public function processForm(array $values): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();

		$identity->setPassword($values['password']);
		$this->_em->flush();

		$canLogin = $identity->isAllowed($this->_fancyAdmin->getCustomerAclResource())
			|| $identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource());

		if ($canLogin) {
			$this->_securityUser->logout(true);
			$this->_securityUser->login($identity, context: $this->_fancyAdmin->getContext());
		}

		$this->getPresenter()->redirect('passwordSet');
	}

	public function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Identity::class);
	}
}
