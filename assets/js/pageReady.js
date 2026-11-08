// Háček pro JS, které čte stav stránky z URL (?activeTab, ?editRecordId …) - README, JS, které čte URL.
// Fancyadmin dělá ajaxem všechny odkazy a URL mění history ext (history.ajax.js) pushState až
// v success, tedy po překreslení snippetů a po live.after. Kód v live.after by proto u odkazu
// bez redirectu četl ještě starou adresu. onPageReady() callback zavolá po načtení stránky
// a po každém AJAX požadavku v complete - ten běží po success všech rozšíření, URL už je nová.

const callbacks = [];

export function onPageReady(callback) {
	callbacks.push(callback);
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => callback());
	} else {
		callback();
	}
}

$.nette.ext('pageReady', {
	complete() {
		callbacks.forEach((callback) => callback());
	},
});
