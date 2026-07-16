<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword;

use ADT\DoctrineAuthenticator\OTP\OnetimeToken;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Nette\Application\UI\InvalidLinkException;
use ReflectionException;

trait ResetPassword
{
	use MailerInject;
	use FancyAdminInject;

	abstract protected function createQueryObject(): QueryObject;

	public function injectResetPassword(): void
	{
		$this->onAnchor[] = function () {
			$this['grid']->addAction('newPasswordAgain', 'Nové heslo', 'newPasswordAgain!')
				->setIcon('lock')
				->setConfirmation(new StringConfirmation('fcadmin.grids.user.confirms.newPassword'))
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

		$identity = $hasIdentity->getIdentity();

		// SSO (Keycloak) uživateli pošleme reset hesla přes Keycloak místo lokálního recovery mailu
		if ($this->_fancyAdmin->isKeycloakEnabled()) {
			$keycloak = $this->_fancyAdmin->getKeycloakManager()?->getInstanceForIdentity($identity);
			if ($keycloak !== null) {
				if ($keycloak->sendPasswordResetEmail($identity, $this->getPresenter()->link('//:Portal:Sign:in'))) {
					$this->getPresenter()->flashMessageSuccess('fcadmin.grids.user.messages.mailSuccess');
				} else {
					$this->getPresenter()->flashMessageError('fcadmin.grids.user.messages.mailError');
				}
				return;
			}
		}

		$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_RECOVERY_VALID_FOR);

		$this->getPresenter()->flashMessageSuccess('fcadmin.grids.user.messages.mailSuccess');
	}
}
