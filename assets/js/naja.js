// AJAX přes Naja (https://naja.js.org). Fancyadmin posílá ajaxem všechny odkazy a formuláře
// v aplikaci, nejen ty s třídou .ajax:
//
// - odkaz bez target a download, s relativní adresou a bez třídy .noajax,
// - formulář bez target, bez třídy .noajax a s adresou na stejném webu.
//
// Klik i odeslání se zachytávají delegovaně na dokumentu, takže ajaxem jdou i odkazy, které
// vykreslil JS (rychlá lišta), a obsluhy, které zavolají preventDefault(), mají přednost.
//
// Historie: do adresního řádku se zapisuje odkaz a formulář odeslaný přes GET. Formulář
// odeslaný přes POST a požadavek z kódu (naja.makeRequest) adresu nemění, kromě přesměrování.
// Adresa bez signálu (signalCsrf.js ho z ní maže), která se rovná současné, nový záznam
// historie nepřidává. Bez signálu se ukládá i do stavu historie, ze kterého čte tlačítko Zpět.

import $ from 'jquery';
import naja from 'naja';
import { cleanUrl } from './signalCsrf';

// datagrid/datagrid.js a projekty hledají globální naja
window.naja = naja;

const LINK_SELECTOR = 'a[href]:not([target]):not([download]):not([href^="http"]):not([href^="//"]):not([href^="#"]):not(.noajax)';
const FORM_SELECTOR = 'form:not([target]):not(.noajax)';

// Vlastní zachytávání níž, Naja sama nic neváže
naja.uiHandler.selector = '[data-fancyadmin-naja-never]';

function isSameOrigin(url) {
	return new URL(url, window.location.href).origin === window.location.origin;
}

function onDomReady(callback) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', callback, { once: true });
	} else {
		callback();
	}
}

const ignoreErrors = () => {
	// chyby obsluhuje naja.addEventListener('error', ...)
};

/** Odkaz, který při kliknutí přebírá Naja (AJAX), i bez třídy .ajax */
export function handlesLink(link) {
	return link.matches(LINK_SELECTOR) && isSameOrigin(link.href);
}

function bindUI() {
	document.addEventListener('click', (e) => {
		if (e.defaultPrevented || e.button || e.altKey || e.ctrlKey || e.shiftKey || e.metaKey) {
			return;
		}
		const link = e.target.closest?.('a[href]');
		if (!link || !handlesLink(link)) {
			return;
		}
		naja.uiHandler.clickElement(link, {}, e).catch(ignoreErrors);
	});

	document.addEventListener('submit', (e) => {
		const form = e.target;
		if (e.defaultPrevented || !form.matches(FORM_SELECTOR) || !isSameOrigin(e.submitter?.getAttribute('formaction') ?? form.action)) {
			return;
		}
		naja.uiHandler.submitForm(form, {}, e).catch(ignoreErrors);
	});
}

// Prvek, který požadavek vyvolal (odkaz, formulář nebo jeho tlačítko) - pro rozšíření níž i v projektech
naja.uiHandler.addEventListener('interaction', (e) => {
	e.detail.options.element = e.detail.element;
});

naja.addEventListener('before', (e) => {
	const { method, options, request } = e.detail;

	// Server podle něj pozná přechod na stránku s jiným layoutem (BasePresenterTrait::beforeRender)
	if (document.body.dataset.fancyadminLayout) {
		request.headers.set('X-Fancyadmin-Layout', document.body.dataset.fancyadminLayout);
	}

	if (options.history === undefined && (!options.element || method.toUpperCase() !== 'GET')) {
		options.history = false;
		options.historyOnRedirect = true;
		return;
	}

	if (options.history !== false && options.href) {
		const href = cleanUrl(options.href) ?? options.href;
		if (new URL(href, window.location.href).href === window.location.href) {
			options.history = 'replace';
		}
	}
});

// Zpět v prohlížeči zopakuje adresu ze stavu historie. Se signálem by po odkazu, který
// otevřel boční panel, jen znovu otevřel panel nad stránkou, na kterou se mezitím odešlo.
naja.historyHandler.addEventListener('buildState', (e) => {
	const { state } = e.detail;
	state.href = cleanUrl(state.href) ?? state.href;
});

naja.addEventListener('payload', (e) => {
	if (e.detail.payload?.forceRedirect) {
		e.detail.options.forceRedirect = true;
	}
});

naja.redirectHandler.addEventListener('redirect', (e) => {
	const { options } = e.detail;
	if (options.historyOnRedirect) {
		options.history = true;
		delete options.historyOnRedirect;
	}
});

// Po odeslání formuláře zůstane stránka odscrollovaná tam, kde byla (překreslí se celé body)
let pendingScroll = null;
let inFlight = 0;

function getScrollContainer() {
	return document.querySelector('[data-app-container]') || document.scrollingElement || document.documentElement;
}

naja.addEventListener('start', (e) => {
	inFlight++;
	if (e.detail.options.element?.form || e.detail.options.element instanceof HTMLFormElement) {
		const container = getScrollContainer();
		pendingScroll = { top: container.scrollTop, left: container.scrollLeft };
	}
});

naja.addEventListener('complete', () => {
	inFlight = Math.max(0, inFlight - 1);
	if (inFlight === 0 && pendingScroll !== null) {
		const scroll = pendingScroll;
		pendingScroll = null;
		const restore = () => {
			const container = getScrollContainer();
			container.scrollTop = scroll.top;
			container.scrollLeft = scroll.left;
		};
		restore();
		window.requestAnimationFrame(restore);
	}
});

/**
 * Zavolá callback pro celý dokument a pak pro každý snippet překreslený ajaxem
 * (náhrada $.nette.ext('live').after). Callback dostane jQuery objekt.
 */
export function onContentLoaded(callback) {
	naja.snippetHandler.addEventListener('afterUpdate', (e) => callback($(e.detail.snippet)));
	callback($(document));
}

/**
 * Zavolá callback pro snippet těsně před jeho překreslením (náhrada $.nette.ext('snippets').before).
 */
export function onBeforeSnippetUpdate(callback) {
	naja.snippetHandler.addEventListener('beforeUpdate', (e) => callback($(e.detail.snippet)));
}

/**
 * GET požadavek z kódu: překreslí snippety a nezruší jiný běžící požadavek. Adresu změní
 * jen přesměrování, viz výše. Parametry z data přidá do adresy požadavku.
 */
export function ajax(url, data = null, options = {}) {
	return naja.makeRequest('GET', url, data, { unique: false, ...options });
}

// Inicializace až po načtení všech modulů, aby se stihla zaregistrovat rozšíření
// fancyadmin i projektu (datagrid poslouchá událost init)
onDomReady(() => {
	// abort: false - Esc zavírá panely a modaly, běžící požadavek (uložení) nesmí zrušit
	naja.initialize({ snippetCache: false, abort: false });
	bindUI();
});

export default naja;
