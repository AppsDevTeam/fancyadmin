<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\BreachedPasswordCheckerInject;
use ADT\FancyAdmin\DI\Injects\ConfigurationQueryFactoryInject;
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
	use AuthenticatorInject;
	use BreachedPasswordCheckerInject;
	use ConfigurationQueryFactoryInject;
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

			// renderValue: false - formular je navazany na Identity, jinak by se do HTML
			// vypsala hodnota sloupce s heslem, tedy jeho hash
			$form->addPasswordReveal('password', false)
				->setHtmlAttribute('placeholder', 'fcadmin.forms.newPassword.labels.password')
				->setRequired('fcadmin.forms.newPassword.errors.required')
				->addRule($form::MinLength, 'fcadmin.forms.newPassword.errors.minLength', 8);

			$form->addPasswordReveal('passwordRepeat', false)
				->setHtmlAttribute('placeholder', 'fcadmin.forms.newPassword.labels.passwordAgain')
				->setRequired('fcadmin.forms.newPassword.errors.required');
		}, 'inputsWrap');

		$form->addSubmit('submit', 'fcadmin.forms.newPassword.labels.submit');
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(array $values, Form $form): void
	{
		if ($values['password'] !== $values['passwordRepeat']) {
			$form->getComponentTextInput('passwordRepeat')->addError('fcadmin.forms.newPassword.errors.noMatch'); // TODO
		}

		if ($this->_breachedPasswordChecker->isBreached($values['password'])) {
			$form->getComponentTextInput('password')->addError('fcadmin.forms.newPassword.errors.breachedPassword');
		}

		$this->validatePasswordPolicy($values['password'], $form);
	}

	private function validatePasswordPolicy(string $password, Form $form): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();

		if ($identity->isAdmin()) {
			$policyKey = 'password.policy.admin';
		} elseif ($identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			$policyKey = 'password.policy.backoffice';
		} else {
			return;
		}

		$config = $this->_configurationQueryFactory->create()->byKey($policyKey)->fetchOneOrNull();
		if (!$config) {
			return;
		}

		$policy = json_decode($config->getValue(), true);
		if (!($policy['enabled'] ?? false)) {
			return;
		}

		$field = $form->getComponentTextInput('password');

		if (mb_strlen($password) < ($policy['minLength'] ?? 8)) {
			$field->addError($this->getTranslator()->translate('fcadmin.forms.newPassword.errors.minLength', $policy['minLength']));
		}
		if (($policy['requireUppercase'] ?? false) && !preg_match('/[A-Z]/', $password)) {
			$field->addError('fcadmin.forms.newPassword.errors.requireUppercase');
		}
		if (($policy['requireLowercase'] ?? false) && !preg_match('/[a-z]/', $password)) {
			$field->addError('fcadmin.forms.newPassword.errors.requireLowercase');
		}
		if (($policy['requireDigit'] ?? false) && !preg_match('/\d/', $password)) {
			$field->addError('fcadmin.forms.newPassword.errors.requireDigit');
		}
		if (($policy['requireSpecialChar'] ?? false) && !preg_match('/[^a-zA-Z0-9]/', $password)) {
			$field->addError('fcadmin.forms.newPassword.errors.requireSpecialChar');
		}
	}

	public function processForm(array $values): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();

		$identity->setPassword($values['password']);
		$this->_em->flush();

		$this->_authenticator->clearIdentity($identity->getAuthObjectId());

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
