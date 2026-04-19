/**
 * Keycloak silent SSO check + token refresh.
 * Volá se při načtení stránky pro přihlášené Keycloak uživatele.
 * Nastavení se čtou z window.__keycloakSettings (injektováno v @layout.latte).
 *
 * Vyžaduje npm balíček `keycloak-js` v projektu.
 */
export async function keycloakLoginSync() {
	const settings = window.__keycloakSettings;

	if (!settings) {
		return;
	}

	delete window.__keycloakSettings;

	try {
		const { default: Keycloak } = await import('keycloak-js');

		const keycloak = new Keycloak({
			url: settings.url,
			realm: settings.realm,
			clientId: settings.clientId
		});

		const authenticated = await keycloak.init({
			onLoad: 'check-sso',
			silentCheckSsoRedirectUri: settings.silentCheckSsoUrl,
			responseMode: 'query'
		});

		if (authenticated) {
			await keycloak.updateToken(60);
		}
	} catch (error) {
		console.error('Failed to initialize Keycloak adapter:', error);
	}
}
