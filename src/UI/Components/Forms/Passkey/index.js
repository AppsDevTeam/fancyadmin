/**
 * PasskeyForm — registrace nového passkey (WebAuthn create ceremony).
 *
 * Formulář v side panelu (add mód) má data-adt-fancyadmin-passkey-form a URL
 * signálů passkeyRegisterArgs / passkeyRegisterVerify v data atributech.
 * Klik na tlačítko spustí ceremony: fetch args → navigator.credentials.create()
 * → POST {name, credential} na verify signál → redirect (reload s flash zprávou).
 *
 * Binárky konvertuje nativní PublicKeyCredential JSON API s base64url fallbackem.
 * Listener je delegovaný na document — funguje i pro side panel vložený AJAXem.
 */

const FORM_SELECTOR = '[data-adt-fancyadmin-passkey-form]';
const BUTTON_SELECTOR = '[data-passkey-register-button]';

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

// Fallback pro prohlížeče bez PublicKeyCredential.parseCreationOptionsFromJSON()
const parseCreationOptions = (publicKey) => {
	if (typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function') {
		return PublicKeyCredential.parseCreationOptionsFromJSON(publicKey);
	}

	const options = { ...publicKey };
	options.challenge = base64UrlToBuffer(publicKey.challenge);
	options.user = { ...publicKey.user, id: base64UrlToBuffer(publicKey.user.id) };
	if (publicKey.excludeCredentials) {
		options.excludeCredentials = publicKey.excludeCredentials.map((cred) => ({
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
			attestationObject: bufferToBase64Url(credential.response.attestationObject),
			transports: typeof credential.response.getTransports === 'function'
				? credential.response.getTransports()
				: [],
		},
	};
};

const showError = (form, message) => {
	let errorEl = form.querySelector('[data-passkey-error]');
	if (!errorEl) {
		errorEl = document.createElement('div');
		errorEl.className = 'alert alert-danger mt-2';
		errorEl.setAttribute('data-passkey-error', '');
		form.appendChild(errorEl);
	}
	errorEl.textContent = message;
	errorEl.classList.remove('d-none');
};

const hideError = (form) => {
	const errorEl = form.querySelector('[data-passkey-error]');
	if (errorEl) {
		errorEl.classList.add('d-none');
	}
};

const register = async (form, button) => {
	const argsUrl = form.getAttribute('data-passkey-register-args-url');
	const verifyUrl = form.getAttribute('data-passkey-register-verify-url');
	const nameInput = form.querySelector('input[name="name"]');
	const name = nameInput ? nameInput.value.trim() : '';

	// Název je povinný — kontrola PŘED ceremony, aby při prázdném poli nezůstal
	// v autentikátoru osiřelý klíč, který by server vzápětí odmítl
	if (name === '') {
		showError(form, form.getAttribute('data-passkey-error-name-required'));
		if (nameInput) {
			nameInput.focus();
		}
		return;
	}

	const argsResponse = await fetch(argsUrl, {
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	});
	const args = await argsResponse.json();
	if (args.error || !args.publicKey) {
		showError(form, args.error || form.getAttribute('data-passkey-error-failed'));
		return;
	}

	const credential = await navigator.credentials.create({
		publicKey: parseCreationOptions(args.publicKey),
	});
	if (!credential) {
		showError(form, form.getAttribute('data-passkey-error-failed'));
		return;
	}

	const verifyResponse = await fetch(verifyUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: JSON.stringify({
			name,
			credential: credentialToJson(credential),
		}),
	});
	const data = await verifyResponse.json();

	if (data.error) {
		showError(form, data.error);
		return;
	}

	if (data.redirect) {
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

	const form = button.closest(FORM_SELECTOR);
	if (!form) {
		return;
	}

	// Ceremony řídí JS — klik nesmí odeslat formulář ani doputovat k nette.ajax
	// handlerům (delegovaný click.nette na :submit na formuláři), proto capture
	// fáze + stopPropagation (viz run()).
	event.preventDefault();
	event.stopPropagation();

	if (ceremonyRunning) {
		return;
	}

	hideError(form);

	if (!window.PublicKeyCredential || !navigator.credentials) {
		showError(form, form.getAttribute('data-passkey-error-unsupported'));
		return;
	}

	ceremonyRunning = true;
	button.disabled = true;
	try {
		await register(form, button);
	} catch (e) {
		// NotAllowedError = uživatel dialog zavřel — bez chybové hlášky.
		// InvalidStateError = klíč pro tento účet už v autentikátoru existuje (excludeCredentials).
		if (!(e instanceof DOMException && e.name === 'NotAllowedError')) {
			showError(form, form.getAttribute('data-passkey-error-failed'));
		}
	} finally {
		ceremonyRunning = false;
		button.disabled = false;
	}
};

// Pojistka: add-mód formulář se nikdy nesmí odeslat standardně (Enter v named poli)
const onSubmit = (event) => {
	const form = event.target.closest ? event.target.closest(FORM_SELECTOR) : null;
	if (form && form.querySelector(BUTTON_SELECTOR)) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}
};

const run = () => {
	// Capture fáze — musí běžet dřív než delegované click.nette handlery
	// nette.ajax na formuláři, jinak klik na submit odešle formulář AJAXem
	document.addEventListener('click', onButtonClick, true);
	document.addEventListener('submit', onSubmit, true);
};

export default { run };
