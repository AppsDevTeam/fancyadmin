/**
 * CSRF token pro signaly administrace (nalez WEB-06).
 *
 * Server dava token jen do adres odkazu na signal, ne do adresy, na kterou odesilaji
 * formulare - viz ADT\FancyAdmin\UI\Presenters\SignalCsrfProtection. Formulare ho proto
 * dostanou az tady, skrytym polem: token prijde cookie `_sec` a odesle se v tele
 * pozadavku, takze nezustava v adresnim radku, v historii prohlizece ani v hlavicce
 * Referer.
 *
 * Formulare s vlastni ochranou (`addProtection()`) jsou z kontroly vyjmute, takze na
 * tomhle skriptu nezavisi. Potrebuji ho filtry a hromadne akce gridu, ktere vlastni
 * token nemaji - bez nej skonci jejich odeslani na 403.
 *
 * Pole se doplnuje hned pri vykresleni, ne az pri odeslani: datagrid odesila formular
 * primo (`naja.uiHandler.submitForm()`, `form.submit()`), takze udalost `submit` by
 * v tu chvili nikdo neodchytil.
 */
const TOKEN_NAME = '_sec';
const SIGNAL_NAME = 'do';

function getToken() {
	const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + TOKEN_NAME + '=([^;]*)'));

	return match ? decodeURIComponent(match[1]) : null;
}

function addTokenToForm(form) {
	const token = getToken();
	const action = new URL(form.action || window.location.href, window.location.href);

	// Cizimu webu token poslat nesmime - prave pred nim chrani.
	if (token === null || action.origin !== window.location.origin) {
		return;
	}

	let input = form.elements.namedItem(TOKEN_NAME);

	if (!(input instanceof HTMLInputElement)) {
		input = document.createElement('input');
		input.type = 'hidden';
		input.name = TOKEN_NAME;
		form.appendChild(input);
	}

	input.value = token;
}

function addTokenToForms(root) {
	if (root instanceof HTMLFormElement) {
		addTokenToForm(root);
	}

	root.querySelectorAll?.('form').forEach(addTokenToForm);
}

/**
 * Adresni radek nema nest ani token, ani signal.
 *
 * Naja po ajaxovem pozadavku prepisuje adresu adresou toho pozadavku - u gridu je to
 * adresa signalu vcetne tokenu. Stav gridu nesou persistentni parametry, takze adresa
 * bez `do` a `_sec` vykresli tutez stranku a navic ji jde znovu nacist, aniz by se
 * signal zopakoval.
 */
function cleanUrl(url) {
	const parsed = new URL(url, window.location.href);

	if (!parsed.searchParams.has(TOKEN_NAME) && !parsed.searchParams.has(SIGNAL_NAME)) {
		return null;
	}

	parsed.searchParams.delete(TOKEN_NAME);
	parsed.searchParams.delete(SIGNAL_NAME);

	return parsed.pathname + parsed.search + parsed.hash;
}

// Prepisuje se primo History API, protoze jinak by zalezelo na tom, jestli se tenhle
// modul nacte driv nez naja a jestli zrovna ona adresu meni.
['pushState', 'replaceState'].forEach((method) => {
	const original = window.history[method];

	window.history[method] = function (state, title, url) {
		return original.call(this, state, title, url === undefined || url === null ? url : (cleanUrl(url) ?? url));
	};
});

document.addEventListener('DOMContentLoaded', () => {
	addTokenToForms(document);

	const cleaned = cleanUrl(window.location.href);

	if (cleaned !== null) {
		window.history.replaceState(window.history.state, '', cleaned);
	}
});

// Formulare prichazeji i ve snippetech, ktere doplnil ajax.
new MutationObserver((records) => {
	records.forEach((record) => {
		record.addedNodes.forEach((node) => {
			if (node instanceof Element) {
				addTokenToForms(node);
			}
		});
	});
}).observe(document.documentElement, { childList: true, subtree: true });
