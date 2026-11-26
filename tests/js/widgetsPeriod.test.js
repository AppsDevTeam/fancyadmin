// Výběr období ve widgetu (assets/js/widgets.js): změna selectu pošle ajaxem adresu s obdobím
// a parametrem redraw, adresu stránky bez redraw zapíše až po úspěšném požadavku.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let naja;
let periodUrls;

const nextTick = () => new Promise((resolve) => setTimeout(resolve));

function widget(name, value, grid = 'dashboard') {
	return `<form class="fa-widgets-period" method="get" action="/admin/">
		<select name="${grid}-${name}-period" data-widget-period data-widget-redraw="${grid}-${name}-redraw">
			<option value="today"${value === 'today' ? ' selected' : ''}>Dnes</option>
			<option value="thisMonth"${value === 'thisMonth' ? ' selected' : ''}>Tento měsíc</option>
		</select>
	</form>`;
}

function choose(select, value) {
	select.value = value;
	select.dispatchEvent(new Event('change', { bubbles: true }));
}

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	({ periodUrls } = await import('../../assets/js/widgets.js'));
});

beforeEach(() => {
	window.history.replaceState(null, '', '/admin/?dashboard-orders-period=lastMonth&filter%5Bstate%5D=2');
	document.body.innerHTML = widget('sales', 'today') + widget('orders', 'thisMonth');
});

afterEach(() => {
	vi.restoreAllMocks();
});

describe('adresy', () => {
	it('adresa stránky dostane období widgetu a zachová ostatní parametry, požadavek navíc redraw', () => {
		const select = document.querySelector('[name="dashboard-sales-period"]');
		select.value = 'thisMonth';

		const { url, request } = periodUrls(select);

		const page = new URL(url);
		expect(page.pathname).toBe('/admin/');
		expect(page.searchParams.get('dashboard-sales-period')).toBe('thisMonth');
		expect(page.searchParams.get('dashboard-orders-period')).toBe('lastMonth');
		expect(page.searchParams.get('filter[state]')).toBe('2');
		expect(page.searchParams.has('dashboard-sales-redraw')).toBe(false);
		expect(new URL(request).searchParams.get('dashboard-sales-redraw')).toBe('1');
	});

	it('změna už zvoleného období přepíše hodnotu, nepřidá druhou', () => {
		window.history.replaceState(null, '', '/admin/?dashboard-sales-period=thisMonth');
		const select = document.querySelector('[name="dashboard-sales-period"]');
		select.value = 'today';

		expect(new URL(periodUrls(select).url).searchParams.getAll('dashboard-sales-period')).toEqual(['today']);
	});
});

describe('změna selectu', () => {
	it('pošle ajax bez zápisu do historie a po odpovědi nahradí adresu', async () => {
		const request = vi.spyOn(naja, 'makeRequest').mockResolvedValue({});

		choose(document.querySelector('[name="dashboard-sales-period"]'), 'thisMonth');

		expect(request).toHaveBeenCalledTimes(1);
		const [method, url, data, options] = request.mock.calls[0];
		expect(method).toBe('GET');
		expect(new URL(url).searchParams.get('dashboard-sales-redraw')).toBe('1');
		expect(data).toBeNull();
		expect(options.history).toBe(false);

		await nextTick();
		expect(window.location.search).toContain('dashboard-sales-period=thisMonth');
		expect(window.location.search).not.toContain('redraw');
	});

	it('neúspěšný požadavek adresu nezmění', async () => {
		vi.spyOn(naja, 'makeRequest').mockRejectedValue(new Error('500'));

		choose(document.querySelector('[name="dashboard-sales-period"]'), 'thisMonth');
		await nextTick();

		expect(window.location.search).not.toContain('dashboard-sales-period');
	});

	it('dvě mřížky se stejným widgetem: změna jedné žádá redraw jen jejího widgetu a období druhé drží', async () => {
		const request = vi.spyOn(naja, 'makeRequest').mockResolvedValue({});
		window.history.replaceState(null, '', '/admin/?customers-sales-period=thisMonth');
		document.body.innerHTML = widget('sales', 'today', 'orders') + widget('sales', 'thisMonth', 'customers');

		choose(document.querySelector('[name="orders-sales-period"]'), 'thisMonth');

		const params = new URL(request.mock.calls[0][1]).searchParams;
		expect(params.get('orders-sales-redraw')).toBe('1');
		expect(params.has('customers-sales-redraw')).toBe(false);
		expect(params.get('customers-sales-period')).toBe('thisMonth');

		await nextTick();
		expect(window.location.search).toBe('?customers-sales-period=thisMonth&orders-sales-period=thisMonth');
	});

	it('jiný select stránky požadavek nespustí', () => {
		const request = vi.spyOn(naja, 'makeRequest').mockResolvedValue({});
		document.body.insertAdjacentHTML('beforeend', '<select name="other"><option value="a">a</option><option value="b">b</option></select>');

		choose(document.querySelector('[name="other"]'), 'b');

		expect(request).not.toHaveBeenCalled();
	});
});

describe('společné období mřížky', () => {
	it('změna odešle formulář mřížky (stránka se načte znovu), ajaxový požadavek widgetu nespustí', () => {
		const request = vi.spyOn(naja, 'makeRequest').mockResolvedValue({});
		document.body.insertAdjacentHTML('beforeend', `<form class="fa-widgets-period" method="get" action="/admin/">
			<select name="dashboard-period" data-widget-grid-period>
				<option value="thisMonth" selected>Tento měsíc</option>
				<option value="thisYear">Tento rok</option>
			</select>
		</form>`);
		const form = document.querySelector('select[name="dashboard-period"]').form;
		const submit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

		choose(document.querySelector('[name="dashboard-period"]'), 'thisYear');

		expect(submit).toHaveBeenCalledTimes(1);
		expect(request).not.toHaveBeenCalled();
	});
});
