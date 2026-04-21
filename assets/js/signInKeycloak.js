/**
 * SignInForm — Keycloak email check.
 *
 * Po opuštění emailového pole ověří, zda email existuje v Keycloaku.
 * Pokud ano, přesměruje na Keycloak login stránku s předvyplněným emailem.
 * Pokud ne, nechá formulář normálně fungovat pro password login.
 *
 * Aktivuje se automaticky, pokud email input obsahuje data atribut `data-keycloak-check-url`.
 */
const run = () => {
	const emailInput = document.getElementById('login-form-input-email');

	if (!emailInput) {
		return;
	}

	const checkUrl = emailInput.getAttribute('data-keycloak-check-url');

	if (!checkUrl) {
		return;
	}

	const passwordInput = document.getElementById('login-form-input-password');
	const submitButton = emailInput.closest('form')?.querySelector('[type="submit"]');

	let checking = false;

	emailInput.addEventListener('change', async function () {
		const email = this.value.trim();

		if (!email || checking) {
			return;
		}

		checking = true;

		// Disable form while checking
		emailInput.readOnly = true;
		if (passwordInput) passwordInput.readOnly = true;
		if (submitButton) submitButton.disabled = true;

		try {
			const url = checkUrl.replace('__EMAIL__', encodeURIComponent(email));
			const response = await fetch(url);
			const data = await response.json();

			if (data.loginUrl) {
				// User exists in Keycloak → redirect to KC login
				window.location.href = data.loginUrl;
				return;
			}
		} catch (e) {
			// Fetch failed, fall through to normal login
		}

		// Re-enable form for normal password login
		emailInput.readOnly = false;
		if (passwordInput) {
			passwordInput.readOnly = false;
			passwordInput.focus();
		}
		if (submitButton) submitButton.disabled = false;
		checking = false;
	});
};

export default { run };
