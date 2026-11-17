// Háček pro JS, které čte stav stránky z URL (?activeTab, ?editRecordId …) - README, JS, které čte URL.
// Fancyadmin dělá ajaxem všechny odkazy a URL mění Naja pushState až v success, po překreslení
// snippetů a po onContentLoaded(). Kód v onContentLoaded() by proto u odkazu bez redirectu četl
// ještě starou adresu. onPageReady() callback zavolá po načtení stránky a po každém AJAX
// požadavku v complete - ten běží po success, URL už je nová.

import naja from 'naja';

const callbacks = [];

export function onPageReady(callback) {
	callbacks.push(callback);
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => callback());
	} else {
		callback();
	}
}

naja.addEventListener('complete', () => {
	callbacks.forEach((callback) => callback());
});
