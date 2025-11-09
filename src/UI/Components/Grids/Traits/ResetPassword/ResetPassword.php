<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword;

use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Nette\Application\UI\InvalidLinkException;
use ReflectionException;

trait ResetPassword
{
	abstract protected function createQueryObject(): BaseQuery;

	public function injectResetPassword(): void
	{
		$this->onAnchor[] = function () {
			$this['grid']->addAction('newPasswordAgain', 'Nové heslo', 'newPasswordAgain!')
				->setIcon('lock')
				->setConfirmation(new StringConfirmation('app.grids.user.confirms.newPassword'))
				->setClass('');	//je potreba, protoze se jinak aplikuje classa btn btn-primary atd. a prida pozadi -> skareda iknka
		};
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws ReflectionException
	 * @throws InvalidLinkException
	 * @throws Exception
	 */
	public function handleNewPasswordAgain(int $id): void
	{
		/** @var HasIdentity $hasIdentity */
		if (!$hasIdentity = $this->createQueryObject()->byId($id)->fetchOneOrNull()) {
			$this->error();
		}

		$this->mailer->sendPasswordRecoveryMail($hasIdentity->getIdentity(), OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);

		$this->getPresenter()->flashMessageSuccess('app.grids.user.messages.mailSuccess');
	}
}
