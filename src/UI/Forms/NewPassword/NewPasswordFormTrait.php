<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms\NewPassword;

use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use ADT\FancyAdmin\UI\Forms\BaseForm;
use App\Model\Enums\AclResourceEnum;
use App\Model\Exceptions\AuthenticationUserNotActiveException;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;

trait NewPasswordFormTrait
{
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

	public function validateForm(array $values): void
	{
		if ($values['password'] !== $values['passwordRepeat']) {
			$this->form->getComponentTextInput('passwordRepeat')->addError('app.forms.newPassword.errors.noMatch');
		}
	}

	public function processForm(ArrayHash $values): void
	{
		$this->securityUser->getIdentity()
			->setPassword(new Passwords()->hash($values->password));

		$this->em->flush();

		$this->presenter->redirect(':Portal:Home:', ['do' => 'redrawBody']);
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	protected function getTemplateFilename(): ?string
	{
		return __DIR__ . '/NewPasswordForm.latte';
	}
}
