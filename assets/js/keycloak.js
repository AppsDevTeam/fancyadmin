/**
 * Keycloak session management pro přihlášené uživatele.
 *
 * - Inicializuje keycloak-js adapter s check-sso
 * - Periodicky refreshuje token (každých 30s)
 * - Při ztrátě session (expirovaný token, odhlášení z KC) odhlásí uživatele z aplikace
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

		keycloak.onTokenExpired = () => {
			keycloak.updateToken(TOKEN_MIN_VALIDITY).catch(() => {
				window.location.href = settings.logoutUrl || '/sign/out';
			});
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
		});

		if (!authenticated) {
			return;
		}

		setInterval(() => {
			keycloak.updateToken(TOKEN_MIN_VALIDITY).catch(() => {
				window.location.href = settings.logoutUrl || '/sign/out';
			});
		}, TOKEN_REFRESH_INTERVAL * 1000);

	} catch (error) {
		console.error('Failed to initialize Keycloak adapter:', error);
	}
}
