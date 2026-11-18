// Dashboard (docs/dashboard.md): výběr období ve widgetu. Změna selectu pošle ajaxem adresu stránky s novým
// obdobím a parametrem redraw, server překreslí jen snippet toho widgetu; adresu bez redraw pak zapíše
// history.replaceState, takže obnovení stránky i sdílený odkaz drží zvolená období. Bez JS odešle
// formulář tlačítko v <noscript>.
import { ajax } from './naja';

const SELECT = 'select[data-dashboard-period]';

/** Adresa stránky se zvoleným obdobím a adresa požadavku, která navíc žádá překreslení widgetu. */
export function periodUrls(select, location = window.location) {
	const url = new URL(location.href);
	url.searchParams.set(select.name, select.value);
	const request = new URL(url);
	request.searchParams.set(select.dataset.dashboardRedraw, '1');
	return { url: url.toString(), request: request.toString() };
}

function onChange(event) {
	const select = event.target instanceof Element ? event.target.closest(SELECT) : null;
	if (!select) {
		return;
	}
	const { url, request } = periodUrls(select);
	ajax(request, null, { history: false })
		.then(() => window.history.replaceState(window.history.state, '', url))
		.catch(() => {});
}

document.addEventListener('change', onChange);

export function stopListening() {
	document.removeEventListener('change', onChange);
}
