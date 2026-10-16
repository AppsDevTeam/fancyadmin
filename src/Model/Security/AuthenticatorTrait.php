<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\OTP\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;
use Nette\Security\AuthenticationException;

trait AuthenticatorTrait
{
	protected FancyAdmin $fancyAdmin;

	public function setFancyAdmin(FancyAdmin $fancyAdmin): void
	{
		$this->fancyAdmin = $fancyAdmin;
	}

	/**
	 * Projekt, ktery stejnym autentizatorem prihlasuje i mimo portal (napr. API pokladny),
	 * si metodu prepise - pravo do portalu tam nedava smysl. Na teto kontrole proto portal
	 * nestoji, kazda cesta do nej si FancyAdmin::canSignIn() vola sama.
	 */
	protected function validateIdentity(Identity $identity, ?string $context = null, array $metadata = []): void
	{
		/** @var \ADT\FancyAdmin\Model\Entities\Identity $identity */
		if (!$this->fancyAdmin->canSignIn($identity)) {
			throw new AuthenticationException('Nemáte oprávnění pro přihlášení');
		}
	}

}
