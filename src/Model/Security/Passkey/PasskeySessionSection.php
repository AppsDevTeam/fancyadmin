<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Passkey;

class PasskeySessionSection
{
	const string SECTION_NAME = 'passkey';

	// Oddělené klíče pro create a get ceremony — souběžná registrace a login si nesmí přepsat challenge
	const string CREATE_CHALLENGE = 'createChallenge';
	const string GET_CHALLENGE = 'getChallenge';

	// Challenge je one-shot (po přečtení se maže) a expiruje
	const string CHALLENGE_EXPIRATION = '5 minutes';

	// Session vznikla klíčem, nebo se v ní klíč zaregistroval (bootstrap). Session bez
	// markeru je u identity s vynuceným 2FA heslová a odhlašuje se (README 19.8).
	const string PASSKEY_SESSION = 'passkeySession';

	// Čeká se na druhý faktor: jen ID identity, nikdy nic, čím by šlo přihlásit (README 19.9)
	const string PENDING_2FA = 'pending2fa';

	const string PENDING_2FA_ATTEMPTS = 'pending2faAttempts';

	// Kdy odešel poslední kód — drží krok s kódem naživu a hlídá prodlevu mezi odesláními
	const string PENDING_2FA_CODE_SENT_AT = 'pending2faCodeSentAt';

	const string PENDING_2FA_EXPIRATION = '10 minutes';

	// Session prošla druhým faktorem jednorázovým kódem, ne klíčem. AuthPresenter ji nechá
	// naživu a při passkeyEnrollmentRequired ji drží na Profilu (README 19.9).
	const string OTP_SESSION = 'otpSession';

	public function getSessionKeys(): array
	{
		return [
			self::CREATE_CHALLENGE,
			self::GET_CHALLENGE,
			self::PASSKEY_SESSION,
			self::PENDING_2FA,
			self::PENDING_2FA_ATTEMPTS,
			self::PENDING_2FA_CODE_SENT_AT,
			self::OTP_SESSION,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
