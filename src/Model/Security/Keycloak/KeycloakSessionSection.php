<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

class KeycloakSessionSection
{
	const string SECTION_NAME              = 'keycloak';

	const string ID_TOKEN                  = 'idToken';
	const string NON_EXISTING_SSO_LOGIN    = 'nonExistingSsoLogin';
	// Pro dočasné uložení URL při logoutu, abysme nemuseli měnit ADT nginx velikosti hlaviček (JWT token je velkej)
	const string LOGOUT_URL                = 'logoutUrl';
	const string AUTH_ATTEMPT_COUNT        = 'authAttemptCount';
	const string AUTH_ATTEMPT_LAST_TIME    = 'authAttemptLastTime';
	const string SSO_INSTANCE_NAME         = 'ssoInstanceName';
	// Názvy SSO instancí, u kterých už v této session proběhl automatický silent SSO pokus
	const string SSO_SILENT_TRIED          = 'ssoSilentTried';
	// Po explicitním odhlášení potlačí jeden následující automatický silent SSO pokus,
	// aby uživatele hned znovu nepřihlásilo (a nešlo se odhlásit)
	const string SSO_SUPPRESS_SILENT       = 'ssoSuppressSilent';


	public function getSessionKeys(): array
	{
		return [
			self::ID_TOKEN,
			self::NON_EXISTING_SSO_LOGIN,
			self::LOGOUT_URL,
			self::AUTH_ATTEMPT_COUNT,
			self::AUTH_ATTEMPT_LAST_TIME,
			self::SSO_INSTANCE_NAME,
			self::SSO_SILENT_TRIED,
			self::SSO_SUPPRESS_SILENT,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
