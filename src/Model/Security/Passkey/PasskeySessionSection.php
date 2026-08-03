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

	public function getSessionKeys(): array
	{
		return [
			self::CREATE_CHALLENGE,
			self::GET_CHALLENGE,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
