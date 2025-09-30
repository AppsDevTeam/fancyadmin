<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Utils\ArrayHash;

trait NewPasswordFormTrait
{
	use FormTrait;
	use RedirectAfterLoginTrait;
	use OnetimeTokenQueryFactoryInject;
	use SecurityUserInject;
	use EntityManagerInject;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addPassword('password')
			->setHtmlAttribute('placeholder', 'app.forms.newPassword.labels.password')
			->setRequired('app.forms.newPassword.errors.required');

		$form->addPassword('passwordRepeat')
			->setHtmlAttribute('placeholder', 'app.forms.newPassword.labels.passwordAgain')
			->setRequired('app.forms.newPassword.errors.required');

		$form->addSubmit('submit', 'app.forms.newPassword.labels.submit');
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(array $values, Form $form): void
	{
		if ($values['password'] !== $values['passwordRepeat']) {
			$form->getComponentTextInput('passwordRepeat')->addError('app.forms.newPassword.errors.noMatch');
		}
	}

	public function processForm(ArrayHash $values): void
	{
		$this->_securityUser->getIdentity()->setPassword($values->password);

		/** @var OnetimeToken $_onetimeToken */
		foreach ($this->_onetimeTokenQueryFactory->create()->byIsValid()->byObjectId($this->_securityUser->getId())->byType(OnetimeToken::TYPE_LOGIN)->fetch() as $_onetimeToken) {
			$_onetimeToken->setUsedAt(new \DateTimeImmutable());
		}

		$this->_em->flush();

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
