<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;

trait AuthenticatorInject
{
	protected DoctrineAuthenticator $_authenticator;
	public function injectAuthenticator(DoctrineAuthenticator $authenticator): void
	{
		$this->_authenticator = $authenticator;
	}
}