<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Forms\SignIn;

use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\User;
use App\Model\Exceptions\AuthenticationUserNotActiveException;
use App\Model\Security\Authenticator;
use ADT\FancyAdmin\Forms\Base\BaseForm;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\AuthenticationException;
use Nette\Utils\ArrayHash;

class SignInForm extends BaseForm
{
	#[Autowire]
	protected Authenticator $authenticator;

	protected ?User $user = null;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addText('email')
			->setHtmlAttribute('id', 'login-form-input-email')
			->setHtmlAttribute('placeholder', 'app.forms.signIn.labels.email')
			->setRequired('app.forms.signIn.errors.emailRequired');

		$form->addPassword('password')
			->setHtmlAttribute('id', 'login-form-input-password')
			->setHtmlAttribute('placeholder', 'app.forms.signIn.labels.password')
			->setRequired('app.forms.signIn.errors.passwordRequired');

		$form->addSubmit('submit', 'app.forms.signIn.labels.logIn')
			->getControlPrototype()->class[] = 'w-100';

		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(ArrayHash $values): void
	{
		try {
			$this->user = $this->authenticator->verifyCredentials($values->email, $values->password);
		} catch (AuthenticationException $e) {
			$this->form->addError('app.forms.signIn.errors.wrongEmailOrPassword');
		} catch (AuthenticationUserNotActiveException $e) {
			$this->form->addError('app.forms.signIn.errors.suspendedAccount');
		}
	}

	/**
	 * @throws AuthenticationException
	 * @throws InvalidLinkException
	 */
	public function processForm(): void
	{
		$this->presenter->user->login($this->user);
		$this->presenter->redirect(':Portal:Dashboard:', ['redrawBody' => true]);
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
