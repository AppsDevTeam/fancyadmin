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
	// Rozpracované autorizační requesty: state (CSRF token) => [verifier, backRedirect, instance, action, time].
	// State se v callbacku ověřuje proti session (one-time use), verifier je PKCE code_verifier.
	const string AUTH_STATES               = 'authStates';
	// Výsledek dokončené Application-Initiated Action: [action, status].
	// Drží se v session (ne v URL), aby nešlo podvrženým odkazem zobrazit cizí bezpečnostní
	// hlášku typu "klíč byl odebrán". Čte se jednorázově přes Keycloak::consumeActionResult().
	const string ACTION_RESULT             = 'actionResult';


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
			self::AUTH_STATES,
			self::ACTION_RESULT,
		];
	}

	public function getSectionName(): string
	{
		return self::SECTION_NAME;
	}
}
