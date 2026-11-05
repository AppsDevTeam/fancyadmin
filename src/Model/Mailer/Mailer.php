<?php

namespace ADT\FancyAdmin\Model\Mailer;

use ADT\FancyAdmin\Model\Entities\Identity;

interface Mailer
{
	/**
	 * @param bool $checkLimit false = bez limitu počtu jednorázových tokenů
	 */
	public function sendAccountCreationEmail(Identity $identity, bool $checkLimit = true): void;

	/**
	 * @param int $tokenLifetime platnost odkazu v hodinách
	 * @param bool $checkLimit false = bez limitu počtu jednorázových tokenů
	 */
	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime, bool $checkLimit = true): void;

	/**
	 * Jednorázový kód pro druhý krok přihlášení při vynuceném 2FA (README 19.9).
	 * Kód generuje implementace v transakci se zápisem tokenu — mail s kódem,
	 * který se neuložil, nesmí odejít.
	 *
	 * @param bool $checkLimit false = bez limitu počtu jednorázových tokenů
	 */
	public function sendTwoFactorCodeMail(Identity $identity, int $tokenLifetimeMinutes, bool $checkLimit = true): void;
}
