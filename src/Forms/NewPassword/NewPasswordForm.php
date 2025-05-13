<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Forms\NewPassword;

use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use App\Model\Exceptions\AuthenticationUserNotActiveException;
use App\Model\Security\Authenticator;
use ADT\FancyAdmin\Forms\Base\BaseForm;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;

class NewPasswordForm extends BaseForm
{
	protected PasswordRecovery $passwordRecovery;

	#[Autowire]
	protected Authenticator $authenticator;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addPassword('password', null)
			->setHtmlAttribute('placeholder', 'app.forms.newPassword.labels.password')
			->setRequired('app.forms.newPassword.errors.required');

		$form->addPassword('passwordRepeat', null)
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
		// Kontrola i pri submitu, mohl byt otevreny prilis dlouho
		if (! $this->passwordRecovery->isValid()) {
			$this->form->addError('app.forms.signIn.errors.expiredRecovery');
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		$this->passwordRecovery->getUser()
			->setPassword((new Passwords())->hash($values->password));
		$this->passwordRecovery->setUsedAt(new \DateTimeImmutable());

		$this->em->flush();

		//tu se musime hned prihlasit
		try {
			$this->securityUser->login($this->passwordRecovery->getUser()->getEmail(), $values->password);
		} catch (AuthenticationException $e) {
			$this->form->addError('app.forms.signIn.errors.wrongEmailOrPassword');
			$this->getPresenter()->redirect(':Portal:Sign:in');
		} catch (AuthenticationUserNotActiveException $e) {
			$this->form->addError('app.forms.signIn.errors.suspendedAccount');
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		$this->presenter->redirect(':Portal:Dashboard:', ['redrawBody' => true]);
	}

	public function setPasswordRecovery(PasswordRecovery $passwordRecovery): self
	{
		$this->passwordRecovery = $passwordRecovery;
		return $this;
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
