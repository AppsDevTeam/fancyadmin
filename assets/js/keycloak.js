/**
 * Keycloak session management pro přihlášené uživatele.
 *
 * - Inicializuje keycloak-js adapter s check-sso
 * - Periodicky refreshuje token (každých 30s)
 * - Při ztrátě session (expirovaný token, odhlášení z KC) odhlásí uživatele z aplikace
 * - Ve skryté záložce (Page Visibility API) refresh pozastavuje, aby session v Keycloaku
 *   neudržovala naživu neaktivní záložka na pozadí — SSO Session Idle tak reálně tiká.
 *   Při návratu do záložky se token hned obnoví; pokud mezitím vypršel i refresh token,
 *   uživatel se odhlásí.
 *
 * Nastavení se čtou z window.__keycloakSettings (injektováno v @layout.latte).
 * Vyžaduje npm balíček `keycloak-js` v projektu.
 */
export async function keycloakLoginSync() {
	const settings = window.__keycloakSettings;

	if (!settings) {
		return;
	}

	delete window.__keycloakSettings;

	const TOKEN_REFRESH_INTERVAL = 30;
	const TOKEN_MIN_VALIDITY = 60;

	try {
		const { default: Keycloak } = await import('keycloak-js');

		const keycloak = new Keycloak({
			url: settings.url,
			realm: settings.realm,
			clientId: settings.clientId
		});

		const refreshToken = () => {
			keycloak.updateToken(TOKEN_MIN_VALIDITY).catch(() => {
				window.location.href = settings.logoutUrl || '/sign/out';
			});
		};

		keycloak.onTokenExpired = () => {
			if (document.hidden) {
				return;
			}
			refreshToken();
		};

		keycloak.onAuthLogout = () => {
			window.location.href = settings.logoutUrl || '/sign/out';
		};

		const authenticated = await keycloak.init({
			onLoad: 'check-sso',
			silentCheckSsoRedirectUri: settings.silentCheckSsoUrl,
			responseMode: 'query',
			checkLoginIframe: true,
			checkLoginIframeInterval: 30,
			// PKCE explicitně — OAuth 2.1 ho vyžaduje i pro public klienty a nechceme
			// spoléhat na default konkrétní verze keycloak-js v projektu
			pkceMethod: 'S256',
		});

		if (!authenticated) {
			return;
		}

		setInterval(() => {
			if (document.hidden) {
				return;
			}
			refreshToken();
		}, TOKEN_REFRESH_INTERVAL * 1000);

		// Po návratu do skryté záložky hned obnovíme token — pokud mezitím vypršel
		// jen access token, uživatel nic nepozná; pokud i refresh token, odhlásí se.
		document.addEventListener('visibilitychange', () => {
			if (!document.hidden) {
				refreshToken();
			}
		});

	} catch (error) {
		console.error('Failed to initialize Keycloak adapter:', error);
	}
}
