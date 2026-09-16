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

	public function getSessionKeys(): array
	{
		return [
			self::CREATE_CHALLENGE,
			self::GET_CHALLENGE,
			self::PASSKEY_SESSION,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
