<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\ChangePassword;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\BreachedPasswordCheckerInject;
use ADT\FancyAdmin\DI\Injects\ConfigurationQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\Forms\PasswordPolicyValidationTrait;
use ADT\Forms\Form;

trait ChangePasswordFormTrait
{
	use AuthenticatorInject;
	use PasswordPolicyValidationTrait;
	use BreachedPasswordCheckerInject;
	use ConfigurationQueryFactoryInject;
	use FancyAdminInject;
	use SecurityUserInject;
	use EntityManagerInject;

	public function initForm(Form $form): void
	{
		// renderValue: false - formular je navazany na Identity, jinak by se do HTML
		// vypsala hodnota sloupce s heslem, tedy jeho hash
		$form->addPasswordReveal('currentPassword', false, 'fcadmin.forms.changePassword.labels.currentPassword')
			->setRequired('fcadmin.forms.changePassword.errors.required');

		$form->addPasswordReveal('password', false, 'fcadmin.forms.changePassword.labels.password')
			->setRequired('fcadmin.forms.changePassword.errors.required')
			->addRule($form::MinLength, 'fcadmin.forms.newPassword.errors.minLength', 8);

		$form->addPasswordReveal('passwordRepeat', false, 'fcadmin.forms.changePassword.labels.passwordRepeat')
			->setRequired('fcadmin.forms.changePassword.errors.required');

		$form->addSubmit('submit', 'fcadmin.forms.changePassword.labels.submit');
	}

	public function validateForm(array $values, Form $form): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();

		if (!password_verify($values['currentPassword'], $identity->getPassword())) {
			$form->getComponentTextInput('currentPassword')->addError('fcadmin.forms.changePassword.errors.wrongPassword');
		}

		if ($values['password'] !== $values['passwordRepeat']) {
			$form->getComponentTextInput('passwordRepeat')->addError('fcadmin.forms.newPassword.errors.noMatch');
		}

		if ($this->_breachedPasswordChecker->isBreached($values['password'])) {
			$form->getComponentTextInput('password')->addError('fcadmin.forms.newPassword.errors.breachedPassword');
		}

		$this->validatePasswordPolicy($values['password'], $form);
	}

	public function processForm(array $values): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();

		$identity->setPassword($values['password']);
		$this->_em->flush();

		$this->_authenticator->clearIdentity($identity->getAuthObjectId());

		$this->_securityUser->logout(true);
		$this->_securityUser->login($identity, context: $this->_fancyAdmin->getContext());
	}

	public function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Identity::class);
	}
}
