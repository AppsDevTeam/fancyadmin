<?php

namespace ADT\FancyAdmin\UI\Components\Forms\LostPassword;

use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Mailer\Mailer;
use ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait;
use ADT\Forms\Form;
use App\Model\Queries\Factories\IdentityQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\LinkGenerator;
use Nette\Utils\ArrayHash;

trait LostPasswordFormTrait
{
	use BaseFormTrait;

	#[Autowire]
	protected Mailer $mailer;

	#[Autowire]
	protected LinkGenerator $linkGenerator;

	private IdentityQueryFactory $_identityQueryFactory;
	public function injectIdentityQueryFactory(\ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory $factory)
	{
		$this->_identityQueryFactory = $factory;
	}

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
		if (!$identity = $this->_identityQueryFactory->create()->byUsername($values['email'])->fetchOneOrNull()) {
			$this->getPresenter()->flashMessageError('fcadmin.forms.lostPassword.messages.error');
			$this->getPresenter()->redirect('this');
		}

		$this->mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);
		$this->getPresenter()->flashMessageSuccess('fcadmin.forms.lostPassword.messages.success');
		$this->getPresenter()->redirect('this');
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	protected function getTemplateFilename(): ?string
	{
		return __DIR__ . '/LostPasswordForm.latte';
	}
}
