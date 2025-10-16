<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword;

use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;

trait ResetPassword
{
	public function injectResetPassword(): void
	{
		$this->onAnchor[] = function () {
			$this['grid']->addAction('newPasswordAgain', 'Nové heslo', 'newPasswordAgain!')
				->setIcon('lock')
				->setConfirmation(new StringConfirmation('app.grids.user.confirms.newPassword'))
				->setClass('');	//je potreba, protoze se jinak aplikuje classa btn btn-primary atd. a prida pozadi -> skareda iknka
		};
	}

	public function handleNewPasswordAgain(int $id): void
	{
		$user = $this->userQueryFactory->create()->byId($id)->fetchOneOrNull();

		if (! $user) {
			$this->error();
		}

		$this->mailer->sendPasswordRecoveryMail($user, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);

		$this->getPresenter()->flashMessageSuccess('app.grids.user.messages.mailSuccess');
	}
}
