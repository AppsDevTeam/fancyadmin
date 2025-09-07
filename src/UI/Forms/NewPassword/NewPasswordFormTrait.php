<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms\NewPassword;

use ADT\FancyAdmin\Model\Queries\OnetimeTokenQuery;
use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use ADT\FancyAdmin\UI\Forms\BaseForm;
use App\Model\Entities\OnetimeToken;
use App\Model\Enums\AclResourceEnum;
use App\Model\Exceptions\AuthenticationUserNotActiveException;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;

trait NewPasswordFormTrait
{
	abstract protected function getOnetimeTokenQuery(): OnetimeTokenQuery;

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
			->setPassword($values->password);

		/** @var \ADT\FancyAdmin\Model\Entities\OnetimeToken $_onetimeToken */
		foreach ($this->getOnetimeTokenQuery()->byIsValid()->byObjectId($this->securityUser->getId())->byType(OnetimeToken::TYPE_LOGIN)->fetch() as $_onetimeToken) {
			$_onetimeToken->setUsedAt(new \DateTimeImmutable());
		}

		$this->em->flush();

		if ($selectedCompany = $this->presenter->user->getIdentity()->getFilteredCompany()?->getId()) {
			$this->presenter->redirect('Home:default', ['do' => 'redrawBody', 'selectedCompany' => $selectedCompany]);
		} else {
			$this->presenter->redirect('Dashboard:default', ['do' => 'redrawBody']);
		}
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
