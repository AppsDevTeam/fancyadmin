<?php

namespace ADT\FancyAdmin\UI\Forms\LostPassword;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Entities\OnetimeTokenTrait;
use ADT\FancyAdmin\Model\Mailer\Mailer;
use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use ADT\FancyAdmin\UI\Forms\BaseForm;
use App\Model\Queries\Factories\IdentityQueryFactory;
use Contributte\Translation\Exceptions\InvalidArgument;
use Doctrine\ORM\Exception\ORMException;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\ArrayHash;

trait LostPasswordForm
{
	abstract protected function getIdentity(string $email): ?Identity;
	
	#[Autowire]
	protected Mailer $mailer;

	#[Autowire]
	protected LinkGenerator $linkGenerator;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addEmail('email', null)
			->setHtmlAttribute('placeholder', 'fcadmin.forms.lostPassword.labels.email')
			->setRequired('fcadmin.forms.lostPassword.errors.emailRequired');

		$form->addSubmit('submit', 'fcadmin.forms.lostPassword.labels.submit');
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn ';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'w-100';
		$form->getComponentSubmitButton('submit')->getControlPrototype()->class[] = 'btn-primary';
	}

	public function processForm(ArrayHash $values): void
	{
		if (!$identity = $this->getIdentity($values['email'])) {
			$this->presenter->flashMessageError('fcadmin.forms.lostPassword.messages.error');
			$this->presenter->redirect('this');
		}
		
		$this->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);
		$this->presenter->flashMessageSuccess('fcadmin.forms.lostPassword.messages.success');
		$this->presenter->redirect('Sign:In');
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
