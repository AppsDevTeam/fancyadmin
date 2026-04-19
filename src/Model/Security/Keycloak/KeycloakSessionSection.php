<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

class KeycloakSessionSection
{
	const string SECTION_NAME              = 'keycloak';

	const string AFTER_LOGIN_SILENT_CHECK  = 'afterLoginSilentCheck';
	const string ID_TOKEN                  = 'idToken';
	const string NON_EXISTING_SSO_LOGIN    = 'nonExistingSsoLogin';
	// Pro dočasné uložení URL při logoutu, abysme nemuseli měnit ADT nginx velikosti hlaviček (JWT token je velkej)
	const string LOGOUT_URL                = 'logoutUrl';
	const string AUTH_ATTEMPT_COUNT        = 'authAttemptCount';
	const string AUTH_ATTEMPT_LAST_TIME    = 'authAttemptLastTime';


	public function getSessionKeys(): array
	{
		return [
			self::AFTER_LOGIN_SILENT_CHECK,
			self::ID_TOKEN,
			self::NON_EXISTING_SSO_LOGIN,
			self::LOGOUT_URL,
			self::AUTH_ATTEMPT_COUNT,
			self::AUTH_ATTEMPT_LAST_TIME,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
