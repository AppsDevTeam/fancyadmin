<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Security\Authenticator;

trait AuthenticatorInject
{
	protected DoctrineAuthenticator $_authenticator;
	public function injectEntityManager(DoctrineAuthenticator $authenticator): void
	{
		$this->_authenticator = $authenticator;
	}
}