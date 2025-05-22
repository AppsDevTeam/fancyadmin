<?php

namespace ADT\FancyAdmin\UI\Forms\LostPassword;

use ADT\Forms\Form;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use App\Model\Queries\Factories\UserQueryFactory;
use App\Model\Services\UserService;
use ADT\FancyAdmin\UI\Forms\Base\BaseForm;
use Doctrine\ORM\Exception\ORMException;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Utils\ArrayHash;

class LostPasswordForm extends \ADT\FancyAdmin\UI\Forms\BaseForm
{
	#[Autowire]
	protected UserService $userService;

	#[Autowire]
	protected UserQueryFactory $userQueryFactory;

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
		if (isset($values['email'])) {
			$user = $this->userQueryFactory->create()->byEmail($values['email'])->fetchOneOrNull();
			if ($user) {
				try {
					$this->userService->sendPasswordRecoveryMail($user, PasswordRecovery::PASSWORD_RECOVERY_VALID_FOR);
					$this->presenter->flashMessageSuccess('fcadmin.forms.lostPassword.messages.success');
				} catch (ORMException $e) {
					$this->presenter->flashMessageError('fcadmin.forms.lostPassword.messages.error');
					$this->presenter->redirect('this');
				}
				$this->presenter->redirect('Sign:In');
			}
		}
		$this->presenter->flashMessageError('fcadmin.forms.lostPassword.messages.error');
		$this->presenter->redirect('this');
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
