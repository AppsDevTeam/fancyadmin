/**
 * SignInForm — Keycloak email check.
 *
 * Po opuštění emailového pole ověří, zda email existuje v Keycloaku.
 * Pokud ano, přesměruje na Keycloak login stránku s předvyplněným emailem.
 * Pokud ne, nechá formulář normálně fungovat pro password login.
 *
 * Aktivuje se pro každý email input s atributem `data-keycloak-check-url`.
 *
 * Listener je navázaný delegovaně na `document`, takže funguje i pro formulář
 * vložený přes AJAX (naja snippet), aniž by se musel znovu inicializovat.
 */
const EMAIL_INPUT_ID = 'login-form-input-email';
const PASSWORD_INPUT_ID = 'login-form-input-password';

let bound = false;

async function handleEmailChange(event) {
	const emailInput = event.target;

	if (!(emailInput instanceof HTMLElement) || emailInput.id !== EMAIL_INPUT_ID) {
		return;
	}

	const checkUrl = emailInput.getAttribute('data-keycloak-check-url');
	if (!checkUrl) {
		return;
	}

	const email = emailInput.value.trim();
	if (!email || emailInput.dataset.keycloakChecking === '1') {
		return;
	}

	const form = emailInput.closest('form');
	const passwordInput = document.getElementById(PASSWORD_INPUT_ID);
	const submitButton = form ? form.querySelector('[type="submit"]') : null;

	// Disable form while checking
	emailInput.dataset.keycloakChecking = '1';
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
	emailInput.dataset.keycloakChecking = '';
}

const run = () => {
	if (bound) {
		return;
	}
	bound = true;
	document.addEventListener('change', handleEmailChange);
};

// Naváže listener hned při importu modulu, aby fungoval i bez explicitního volání run()
// (např. když je formulář na stránce vložen až AJAXem po prvotním načtení).
run();

export default { run };
