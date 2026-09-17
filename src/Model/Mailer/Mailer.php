<?php

namespace ADT\FancyAdmin\Model\Mailer;

use ADT\FancyAdmin\Model\Entities\Identity;

interface Mailer
{
	public function sendAccountCreationEmail(Identity $identity): void;
	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime): void;

	/**
	 * Jednorázový kód pro druhý krok přihlášení při vynuceném 2FA (README 19.9).
	 * Kód generuje implementace v transakci se zápisem tokenu — mail s kódem,
	 * který se neuložil, nesmí odejít.
	 */
	public function sendTwoFactorCodeMail(Identity $identity, int $tokenLifetimeMinutes): void;
}
