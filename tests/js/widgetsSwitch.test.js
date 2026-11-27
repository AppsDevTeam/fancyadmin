// Přepínače ve widgetu a seznam s limitem (assets/js/widgets.js): klik na volbu pošle ajaxem aktuální adresu
// s novou volbou a redraw, adresu zapíše a volbu označí až po úspěšném požadavku; „Zobrazit všech“ ukáže skryté.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let naja;
let switchUrls;

const nextTick = () => new Promise((resolve) => setTimeout(resolve));
const click = (element) => element.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));

const option = (name, value, fallback, active) => `<a href="/admin/?stale=1" class="btn noajax ${active ? 'btn-primary' : 'btn-outline-primary'}"${active ? ' aria-current="true"' : ''}
	data-widget-switch="dashboard-trend-switch[${name}]" data-widget-value="${value}" data-widget-default="${fallback}" data-widget-redraw="dashboard-trend-redraw">${value}</a>`;

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	({ switchUrls } = await import('../../assets/js/widgets.js'));
});

beforeEach(() => {
	window.history.replaceState(null, '', '/admin/reservations?dashboard-period=thisYear&dashboard-trend-switch%5Brange%5D=36');
	document.body.innerHTML = `<div class="fa-widgets-switches">
		<div class="btn-group fa-widgets-switch">${option('unit', 'ks', 'ks', true)}${option('unit', 'kc', 'ks', false)}</div>
		<div class="btn-group fa-widgets-switch">${option('range', '12', '12', false)}${option('range', '36', '12', true)}</div>
	</div>
	<ul class="fa-widgets-alerts"><li>1</li><li class="fa-widgets-alert-extra" hidden>2</li><li class="fa-widgets-alert-extra" hidden>3</li></ul>
	<button type="button" class="fa-widgets-more" data-widget-more aria-expanded="false" data-open-label="Zobrazit všech 3" data-close-label="Zobrazit méně">Zobrazit všech 3</button>`;
});

afterEach(() => {
	vi.restoreAllMocks();
});

describe('přepínač', () => {
	it('adresa je aktuální stránka s novou volbou, ostatní parametry zůstanou; výchozí volba parametr odebere', () => {
		const { url, request } = switchUrls(document.querySelector('[data-widget-value="kc"]'));
		const page = new URL(url);
		expect(page.searchParams.get('dashboard-trend-switch[unit]')).toBe('kc');
		expect(page.searchParams.get('dashboard-trend-switch[range]')).toBe('36');
		expect(page.searchParams.get('dashboard-period')).toBe('thisYear');
		expect(page.searchParams.has('stale')).toBe(false);
		expect(new URL(request).searchParams.get('dashboard-trend-redraw')).toBe('1');

		const back = new URL(switchUrls(document.querySelector('[data-widget-value="12"]')).url);
		expect(back.searchParams.has('dashboard-trend-switch[range]')).toBe(false);
	});

	it('klik pošle ajax s redraw, pak zapíše adresu a označí volbu', async () => {
		const request = vi.spyOn(naja, 'makeRequest').mockResolvedValue({});
		const kc = document.querySelector('[data-widget-value="kc"]');
		click(kc);
		await nextTick();

		expect(request).toHaveBeenCalledTimes(1);
		expect(new URL(request.mock.calls[0][1]).searchParams.get('dashboard-trend-redraw')).toBe('1');
		expect(window.location.search).toContain('dashboard-trend-switch%5Bunit%5D=kc');
		expect(kc.classList.contains('btn-primary')).toBe(true);
		expect(kc.getAttribute('aria-current')).toBe('true');
		const ks = document.querySelector('[data-widget-value="ks"]');
		expect(ks.classList.contains('btn-outline-primary')).toBe(true);
		expect(ks.hasAttribute('aria-current')).toBe(false);
		// druhá skupina se nezměnila
		expect(document.querySelector('[data-widget-value="36"]').classList.contains('btn-primary')).toBe(true);
	});

	it('neúspěšný požadavek adresu ani volbu nezmění', async () => {
		vi.spyOn(naja, 'makeRequest').mockRejectedValue(new Error('500'));
		click(document.querySelector('[data-widget-value="kc"]'));
		await nextTick();

		expect(window.location.search).not.toContain('unit');
		expect(document.querySelector('[data-widget-value="ks"]').classList.contains('btn-primary')).toBe(true);
	});
});

describe('seznam s limitem', () => {
	it('Zobrazit všech ukáže skryté položky, Zobrazit méně je zase skryje', () => {
		const button = document.querySelector('[data-widget-more]');
		click(button);
		expect([...document.querySelectorAll('.fa-widgets-alert-extra')].every((item) => !item.hidden)).toBe(true);
		expect(button.textContent).toBe('Zobrazit méně');
		expect(button.getAttribute('aria-expanded')).toBe('true');

		click(button);
		expect([...document.querySelectorAll('.fa-widgets-alert-extra')].every((item) => item.hidden)).toBe(true);
		expect(button.textContent).toBe('Zobrazit všech 3');
	});
});
