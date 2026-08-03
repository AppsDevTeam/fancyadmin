/**
 * SignInForm — přihlášení passkey (WebAuthn).
 *
 * Ceremony se spouští VÝHRADNĚ kliknutím na tlačítko "Přihlásit se přihlašovacím
 * klíčem" (usernameless get, prázdné allowCredentials). Žádná automatika:
 * - passkey se nenabízí sám v autofillu email pole (conditional mediation)
 * - dokud uživatel neklikne, nejde na server žádný request, takže anonymní
 *   návštěvník login stránky nedostane ani session cookie
 *
 * Binárky konvertuje nativní PublicKeyCredential JSON API s base64url fallbackem
 * pro starší prohlížeče.
 *
 * Listener je delegovaný na document, takže funguje i pro formulář vložený přes
 * AJAX snippet. Aktivace přes data-adt-fancyadmin-passkey-login.
 */

const ROOT_SELECTOR = '[data-adt-fancyadmin-passkey-login]';
const BUTTON_SELECTOR = '[data-passkey-login-button]';

let ceremonyRunning = false;

const base64UrlToBuffer = (base64Url) => {
	const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
	const binary = window.atob(base64);
	const buffer = new Uint8Array(binary.length);
	for (let i = 0; i < binary.length; i++) {
		buffer[i] = binary.charCodeAt(i);
	}
	return buffer;
};

const bufferToBase64Url = (buffer) => {
	const bytes = new Uint8Array(buffer);
	let binary = '';
	for (const byte of bytes) {
		binary += String.fromCharCode(byte);
	}
	return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

// Fallback pro prohlížeče bez PublicKeyCredential.parseRequestOptionsFromJSON()
const parseRequestOptions = (publicKey) => {
	if (typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function') {
		return PublicKeyCredential.parseRequestOptionsFromJSON(publicKey);
	}

	const options = { ...publicKey };
	options.challenge = base64UrlToBuffer(publicKey.challenge);
	if (publicKey.allowCredentials) {
		options.allowCredentials = publicKey.allowCredentials.map((cred) => ({
			...cred,
			id: base64UrlToBuffer(cred.id),
		}));
	}
	return options;
};

// Fallback pro prohlížeče bez PublicKeyCredential.prototype.toJSON()
const credentialToJson = (credential) => {
	if (typeof credential.toJSON === 'function') {
		return credential.toJSON();
	}

	return {
		id: credential.id,
		rawId: bufferToBase64Url(credential.rawId),
		type: credential.type,
		response: {
			clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
			authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
			signature: bufferToBase64Url(credential.response.signature),
			userHandle: credential.response.userHandle ? bufferToBase64Url(credential.response.userHandle) : null,
		},
	};
};

const showError = (root, message) => {
	const errorEl = root.querySelector('[data-passkey-error]');
	if (errorEl && message) {
		errorEl.textContent = message;
		errorEl.classList.remove('d-none');
	}
};

const hideError = (root) => {
	const errorEl = root.querySelector('[data-passkey-error]');
	if (errorEl) {
		errorEl.classList.add('d-none');
	}
};

const authenticate = async (root) => {
	const argsUrl = root.getAttribute('data-passkey-args-url');
	const verifyUrl = root.getAttribute('data-passkey-verify-url');

	const argsResponse = await fetch(argsUrl, {
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	});
	const args = await argsResponse.json();
	if (args.error || !args.publicKey) {
		throw new Error(args.error || 'invalid args');
	}

	const credential = await navigator.credentials.get({
		publicKey: parseRequestOptions(args.publicKey),
	});
	if (!credential) {
		throw new Error('no credential');
	}

	const verifyResponse = await fetch(verifyUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: JSON.stringify(credentialToJson(credential)),
	});

	let data = null;
	try {
		data = await verifyResponse.json();
	} catch (e) {
		// non-JSON odpověď (např. ForwardResponse po restoreRequest) — uživatel už je přihlášen
	}

	if (data && data.error) {
		showError(root, data.error);
		return;
	}

	if (data && data.redirect) {
		window.location.href = data.redirect;
	} else {
		window.location.reload();
	}
};

const onButtonClick = async (event) => {
	const button = event.target.closest(BUTTON_SELECTOR);
	if (!button) {
		return;
	}

	const root = button.closest(ROOT_SELECTOR);
	if (!root || ceremonyRunning) {
		return;
	}

	hideError(root);

	if (!window.PublicKeyCredential || !navigator.credentials) {
		showError(root, root.getAttribute('data-passkey-error-unsupported'));
		return;
	}

	ceremonyRunning = true;
	button.disabled = true;
	try {
		await authenticate(root);
	} catch (e) {
		// NotAllowedError = uživatel dialog zavřel — bez chybové hlášky
		if (!(e instanceof DOMException && e.name === 'NotAllowedError')) {
			showError(root, root.getAttribute('data-passkey-error-failed'));
		}
	} finally {
		ceremonyRunning = false;
		button.disabled = false;
	}
};

const run = () => {
	document.addEventListener('click', onButtonClick);
};

export default { run };
