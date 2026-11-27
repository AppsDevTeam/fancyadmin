// Stíny přilepených sloupců a vodorovná lišta gridu: řídí se tabulkou, ne klonem přilepené hlavičky, a posluchače se navážou jednou
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let naja;
let observers = [];

// jsdom nemá layout ani ResizeObserver: rozměry nastavuje test, změnu velikosti ohlásí resize()
class FakeResizeObserver {
	constructor(callback) {
		this.callback = callback;
		observers.push(this);
	}

	observe() {
		this.callback([]);
	}

	disconnect() {}
}

function size(el, scrollWidth, clientWidth) {
	Object.defineProperty(el, 'scrollWidth', { configurable: true, value: scrollWidth });
	Object.defineProperty(el, 'clientWidth', { configurable: true, value: clientWidth });
}

const clone = () => document.querySelector('.datagrid-sticky-thead__inner');
const responsive = () => document.querySelector('.table-responsive-wrapper > .table-responsive');
const bar = () => document.querySelector('.table-bottom-scroll');
const resize = () => observers.forEach((observer) => observer.callback([]));
const shadow = (el, side) => el.querySelector('table').style.getPropertyValue(`--actionShadowWidth${side}`);

function load() {
	const snippet = document.getElementById('snippet-grid-grid');
	naja.snippetHandler.dispatchEvent(new CustomEvent('afterUpdate', { detail: { snippet } }));
}

beforeAll(async () => {
	globalThis.ResizeObserver = FakeResizeObserver;
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	await import('../../assets/js/tableActionsShadow.js');
});

beforeEach(() => {
	observers = [];
	document.body.innerHTML = `<div id="snippet-grid-grid"><div class="table-responsive-wrapper">
		<div class="datagrid-sticky-thead"><div class="table-responsive datagrid-sticky-thead__inner"><table></table></div></div>
		<div class="table-responsive"><table></table></div>
		<div class="table-bottom-scroll"><div class="table-bottom-scroll-inner"></div></div>
	</div></div>`;
	size(bar(), 1154, 1154);
});

describe('vodorovná lišta gridu', () => {
	it('tabulka se vejde: lišta schovaná, i když klon hlavičky drží starou širší šířku', () => {
		size(clone(), 1334, 1174);
		size(responsive(), 1174, 1174);
		load();

		expect(bar().classList.contains('d-none')).toBe(true);
	});

	it('tabulka přetéká: lišta vidět a posouvá se o stejný kus jako tabulka', () => {
		size(clone(), 1174, 1174);
		size(responsive(), 1300, 1174);
		load();

		expect(bar().classList.contains('d-none')).toBe(false);
		expect(bar().querySelector('.table-bottom-scroll-inner').style.width).toBe(1154 + 126 + 'px');
	});

	it('změna šířky tabulky bez resize okna lištu schová', () => {
		size(responsive(), 1300, 1174);
		load();
		size(responsive(), 1300, 1300);
		resize();

		expect(bar().classList.contains('d-none')).toBe(true);
	});
});

describe('stíny přilepených sloupců', () => {
	it('tabulka přetéká doprava: stín u sloupce akcí má tabulka i klon hlavičky', () => {
		size(responsive(), 1300, 1174);
		load();

		expect(shadow(responsive(), 'Right')).toBe('30px');
		expect(shadow(clone(), 'Right')).toBe('30px');
		expect(shadow(responsive(), 'Left')).toBe('0px');
	});

	it('opakované překreslení posluchače nepřidá', () => {
		size(responsive(), 1174, 1174);
		const listen = vi.spyOn(responsive(), 'addEventListener');
		load();
		load();
		load();

		// Jeden pro stíny, jeden pro vodorovnou lištu
		expect(listen.mock.calls.filter(([type]) => type === 'scroll')).toHaveLength(2);
		expect(clone().dataset.shadowBound).toBeUndefined();
	});
});
