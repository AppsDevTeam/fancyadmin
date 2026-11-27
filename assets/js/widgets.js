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

/**
 * Panel otevíraný odkazem jiné karty (Widget::setToggle / setPanel): „Seznam nabídek ▾“ panel ukáže, znovu
 * nebo „Zavřít seznam“ ho skryje. Ve skupině panelů je otevřený nejvýš jeden; karta s otevřeným panelem
 * má zvýrazněný okraj a odkaz „Skrýt seznam ▴“.
 */
// jméno widgetu do selektoru atributu (jména jsou [a-zA-Z0-9_], CSS.escape jen pro jistotu - v jsdom chybí)
const esc = (value) => (window.CSS?.escape ? window.CSS.escape(String(value)) : String(value).replace(/["\\]/g, '\\$&'));

export function togglePanel(target, root = document) {
	const panel = root.querySelector(`[data-widget="${esc(target)}"][data-widget-panel]`);
	if (!panel) {
		return;
	}
	const open = panel.hidden;
	if (open) {
		root.querySelectorAll(`[data-widget-panel="${esc(panel.dataset.widgetPanel)}"]`).forEach((other) => {
			if (other !== panel && !other.hidden) {
				setPanel(other, false, root);
			}
		});
	}
	setPanel(panel, open, root);
	if (open) {
		panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}
}

function setPanel(panel, open, root) {
	panel.hidden = !open;
	const target = panel.dataset.widget;
	root.querySelectorAll(`.fa-widgets-toggle[data-widget-toggle="${esc(target)}"]`).forEach((button) => {
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		const label = button.querySelector('.fa-widgets-toggle-label');
		if (label) {
			label.textContent = open ? button.dataset.closeLabel : button.dataset.openLabel;
		}
	});
	root.querySelectorAll(`[data-widget-toggler="${esc(target)}"]`).forEach((card) => {
		card.classList.toggle('fa-widgets-toggler-open', open);
	});
}

/** Vnořené řádky tabulky (TableWidget::setRowChildren): klik na řádek se šipkou je ukáže / skryje. */
export function toggleRow(parent) {
	const button = parent.querySelector('[data-widget-row-toggle]');
	const open = button?.getAttribute('aria-expanded') !== 'true';
	button?.setAttribute('aria-expanded', open ? 'true' : 'false');
	parent.closest('tbody')?.querySelectorAll(`[data-widget-row-child="${esc(parent.dataset.widgetRow)}"]`).forEach((row) => {
		row.hidden = !open;
	});
}

/**
 * Seznam s limitem (AlertsWidget::setLimit): „Zobrazit všech N“ ukáže skryté položky, „Zobrazit méně“ je zase
 * skryje.
 */
export function toggleMore(button) {
	const list = button.previousElementSibling;
	const open = button.getAttribute('aria-expanded') !== 'true';
	list?.querySelectorAll('.fa-widgets-alert-extra').forEach((item) => {
		item.hidden = !open;
	});
	button.setAttribute('aria-expanded', open ? 'true' : 'false');
	button.textContent = open ? button.dataset.closeLabel : button.dataset.openLabel;
}

/**
 * Přepínač ve widgetu (Widget::addSwitch): adresa je aktuální adresa stránky s novou volbou (výchozí volba
 * parametr odebere) - ostatní přepínače a období zůstanou, i když se mezitím změnily bez načtení stránky.
 * Jako u období se ajaxem překreslí jen widget, adresu zapíše replaceState a tlačítko se označí jako zvolené.
 */
export function switchUrls(link, location = window.location) {
	const url = new URL(location.href);
	const { widgetSwitch: name, widgetValue: value, widgetDefault: fallback } = link.dataset;
	if (value === fallback) {
		url.searchParams.delete(name);
	} else {
		url.searchParams.set(name, value);
	}
	const request = new URL(url);
	request.searchParams.set(link.dataset.widgetRedraw, '1');
	return { url: url.toString(), request: request.toString() };
}

export function markSwitch(link) {
	link.closest('.fa-widgets-switch')?.querySelectorAll('a[data-widget-switch]').forEach((other) => {
		const active = other === link;
		other.classList.toggle('btn-primary', active);
		other.classList.toggle('btn-outline-primary', !active);
		if (active) {
			other.setAttribute('aria-current', 'true');
		} else {
			other.removeAttribute('aria-current');
		}
	});
}

// Modul může být v bundlu dvakrát (app.js fancyadminu i entry point projektu) - posluchač jen jednou,
// jinak by se panel i řádek přepnuly dvakrát a zůstaly zavřené
function onClick(event) {
	const target = event.target instanceof Element ? event.target : null;
	const more = target?.closest('[data-widget-more]');
	if (more) {
		event.preventDefault();
		toggleMore(more);
		return;
	}
	const switchLink = target?.closest('a[data-widget-switch]');
	if (switchLink && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
		event.preventDefault();
		const { url, request } = switchUrls(switchLink);
		ajax(request, null, { history: false })
			.then(() => {
				window.history.replaceState(window.history.state, '', url);
				markSwitch(switchLink);
			})
			.catch(() => {});
		return;
	}
	const toggle = target?.closest('[data-widget-toggle]');
	if (toggle) {
		event.preventDefault();
		togglePanel(toggle.dataset.widgetToggle);
		return;
	}
	const parent = target?.closest('tr[data-widget-row]');
	if (parent && !target.closest('a')) {
		toggleRow(parent);
	}
}

if (!window.__fancyadminWidgetsClick) {
	window.__fancyadminWidgetsClick = true;
	document.addEventListener('click', onClick);
}
