// Widgety (docs/widgets.md): výběr období ve widgetu a grafy. Změna selectu pošle ajaxem adresu stránky
// s novým obdobím a parametrem redraw, server překreslí jen snippet toho widgetu; adresu bez redraw pak zapíše
// history.replaceState, takže obnovení stránky i sdílený odkaz drží zvolená období. Bez JS odešle
// formulář tlačítko v <noscript>. Graf v překresleném widgetu se nevymění, jen dostane nová data.
import AdtJsComponents from 'adt-js-components';
import naja, { ajax } from './naja';
import { fancyadminTheme } from './widgetsTheme';

AdtJsComponents.registerChartPlugins({ fancyadminTheme });

const SELECT = 'select[data-widget-period]';

/** Adresa stránky se zvoleným obdobím a adresa požadavku, která navíc žádá překreslení widgetu. */
export function periodUrls(select, location = window.location) {
	const url = new URL(location.href);
	url.searchParams.set(select.name, select.value);
	const request = new URL(url);
	request.searchParams.set(select.dataset.widgetRedraw, '1');
	return { url: url.toString(), request: request.toString() };
}

const GRID_SELECT = 'select[data-widget-grid-period]';

function onChange(event) {
	const target = event.target instanceof Element ? event.target : null;
	// Společné období mřížky mění skoro všechny widgety - odešle se formulář a stránka se načte znovu
	const gridSelect = target?.closest(GRID_SELECT);
	if (gridSelect) {
		gridSelect.form?.requestSubmit();
		return;
	}
	const select = target?.closest(SELECT);
	if (!select) {
		return;
	}
	const { url, request } = periodUrls(select);
	ajax(request, null, { history: false })
		.then(() => window.history.replaceState(window.history.state, '', url))
		.catch(() => {});
}

document.addEventListener('change', onChange);

/**
 * Snippet widgetu s grafem, jehož nový obsah je zase graf: místo výměny HTML se jen přepíše data-adt-chart
 * stávajícího canvasu, builtin Chart pak graf aktualizuje na místě (bez probliknutí a s animací přechodu).
 */
export function chartUpdateOperation(snippet, content) {
	if (!snippet.closest('[data-widget]')) {
		return null;
	}
	const canvas = snippet.querySelectorAll('canvas[data-adt-chart]');
	const next = document.createElement('template');
	next.innerHTML = content;
	const nextCanvas = next.content.querySelectorAll('canvas[data-adt-chart]');
	if (canvas.length !== 1 || nextCanvas.length !== 1) {
		return null;
	}
	return () => canvas[0].setAttribute('data-adt-chart', nextCanvas[0].getAttribute('data-adt-chart'));
}

naja.snippetHandler.addEventListener('beforeUpdate', (event) => {
	const operation = chartUpdateOperation(event.detail.snippet, event.detail.content);
	if (operation) {
		event.detail.changeOperation(operation);
	}
});

export function stopListening() {
	document.removeEventListener('change', onChange);
}
