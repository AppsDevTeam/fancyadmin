<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms\SignIn;

use ADT\FancyAdmin\Model\Administration;
use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\User;
use App\Model\Exceptions\AuthenticationUserNotActiveException;
use ADT\FancyAdmin\UI\Forms\Base\BaseForm;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;

class SignInForm extends \ADT\FancyAdmin\UI\Forms\BaseForm
{
	protected Authenticator $authenticator;

	protected Administration $administration;

	protected $user = null;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addText('email')
			->setHtmlAttribute('id', 'login-form-input-email')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.email')
			->setRequired('fcadmin.forms.signIn.errors.emailRequired');

		$form->addPassword('password')
			->setHtmlAttribute('id', 'login-form-input-password')
			->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.password')
			->setRequired('fcadmin.forms.signIn.errors.passwordRequired');

		$form->addSubmit('submit', 'fcadmin.forms.signIn.labels.logIn')
			->getControlPrototype()->class[] = 'w-100';

		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function validateForm(ArrayHash $values): void
	{
		try {
			$this->user = $this->authenticator->verifyCredentials($values->email, $values->password);
		} catch (AuthenticationException $e) {
			$this->form->addError('fcadmin.forms.signIn.errors.wrongEmailOrPassword');
		} catch (AuthenticationUserNotActiveException $e) {
			$this->form->addError('fcadmin.forms.signIn.errors.suspendedAccount');
		}
	}

	/**
	 * @throws AuthenticationException
	 * @throws InvalidLinkException
	 */
	public function processForm(): void
	{
		$this->presenter->user->login($this->user);
		$this->presenter->redirect($this->administration->getHomepagePresenter(), ['redrawBody' => true]);
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	public function setAuthenticator(Authenticator $authenticator): self
	{
		$this->authenticator = $authenticator;
		return $this;
	}

	public function setAdministration(Administration $administration): SignInForm
	{
		$this->administration = $administration;
		return $this;
	}

}
