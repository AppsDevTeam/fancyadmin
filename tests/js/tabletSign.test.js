// Stránka podpisu dokumentů na tabletu (assets/js/tabletSign.js, createSignState): podpis
// nakreslený jednou jde potvrdit do dalších míst, nový podpis platí jen pro místo, kde vznikl,
// a odeslat jde až všechna místa všech dokumentů.
// (pdf.js a plátno z import mapy nahrazují stuby, vitest.config.mjs)
import { describe, expect, it, vi } from 'vitest';
import { createSignState, initDragPan } from '../../assets/js/tabletSign.js';

const PLACES = [
	{ id: 'contract:0', document: 'contract' },
	{ id: 'contract:2', document: 'contract' },
	{ id: 'consent:-', document: 'consent' },
];
const FIRST = { image: 'data:image/png;base64,AAA', biometrics: '{"v":1}' };
const SECOND = { image: 'data:image/png;base64,BBB', biometrics: '{"v":2}' };

describe('createSignState', () => {
	it('převezme předchozí podpis do dalšího místa a odešle ho jednou', () => {
		const state = createSignState(PLACES);
		expect(state.getLast('contract:2')).toBeNull();

		state.sign('contract:0', FIRST);
		expect(state.getLast('contract:2')).toEqual(FIRST);
		state.reuse('contract:2');
		state.sign('consent:-', SECOND);

		expect(state.isComplete()).toBe(true);
		expect(state.toJSON()).toEqual({
			signatures: { s1: FIRST, s2: SECOND },
			placements: { 'contract:0': 's1', 'contract:2': 's1', 'consent:-': 's2' },
		});
	});

	it('nový podpis platí jen pro své místo, přepsaný podpis se neodesílá', () => {
		const state = createSignState(PLACES);
		state.sign('contract:0', FIRST);
		state.reuse('contract:2');

		// podepsal se znovu jen do druhého místa
		state.sign('contract:2', SECOND);
		expect(state.get('contract:0')).toEqual(FIRST);
		expect(state.get('contract:2')).toEqual(SECOND);
		expect(state.getLast('consent:-')).toEqual(SECOND);

		// a pak znovu i do prvního - první podpis už v žádném místě není
		state.sign('contract:0', SECOND);
		expect(Object.keys(state.toJSON().signatures)).toEqual(['s3', 's2']);
	});

	it('počítá podepsaná místa celkem i po dokumentech a najde další nepodepsané', () => {
		const state = createSignState(PLACES);
		expect(state.progress()).toEqual({ signed: 0, total: 3 });
		expect(state.nextUnsigned()).toEqual(PLACES[0]);

		state.sign('contract:2', FIRST);
		expect(state.progress('contract')).toEqual({ signed: 1, total: 2 });
		expect(state.progress('consent')).toEqual({ signed: 0, total: 1 });
		expect(state.isComplete()).toBe(false);

		// od naposledy ukázaného místa dál, pak znovu od začátku
		expect(state.nextUnsigned('contract:0')).toEqual(PLACES[2]);
		expect(state.nextUnsigned('consent:-')).toEqual(PLACES[0]);
	});

	it('podpis jednoho podepisujícího se druhému nenabídne', () => {
		const state = createSignState([
			{ id: 'contract:0', document: 'contract', signer: 'Jan Novák' },
			{ id: 'contract:1', document: 'contract', signer: 'Eva Nováková' },
			{ id: 'consent:-', document: 'consent', signer: 'Jan Novák' },
		]);
		state.sign('contract:0', FIRST);

		expect(state.getLast('contract:1')).toBeNull();
		expect(() => state.reuse('contract:1')).toThrow();
		expect(state.getLast('consent:-')).toEqual(FIRST);

		state.sign('contract:1', SECOND);
		state.reuse('consent:-');
		expect(state.get('consent:-')).toEqual(FIRST);
	});

	it('převzít jde až nějaký podpis', () => {
		expect(() => createSignState(PLACES).reuse('contract:0')).toThrow();
	});
});

// Přiblížený dokument jde myší chytit a táhnout (initDragPan) - tah se od klepnutí na místo pro
// podpis liší pohybem, prst posouvá prohlížeč sám
describe('initDragPan', () => {
	function pointer(type, target, x, y, pointerType = 'mouse') {
		const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0 });
		Object.defineProperty(event, 'pointerType', { value: pointerType });
		target.dispatchEvent(event);
	}

	function page() {
		document.body.innerHTML = '<div class="tablet-sign-documents"><div data-tablet-document><button data-place>místo</button></div></div>';
		const area = document.querySelector('.tablet-sign-documents');
		const scroller = area.querySelector('[data-tablet-document]');
		scroller.scrollLeft = 300;
		window.scrollTo = vi.fn();
		return { area, scroller, place: area.querySelector('[data-place]') };
	}

	it('tah posune dokument a klepnutí, které ho ukončilo, zahodí', () => {
		const { area, scroller, place } = page();
		initDragPan(area, () => true);
		const clicked = vi.fn();
		place.addEventListener('click', clicked);

		pointer('pointerdown', place, 500, 400);
		pointer('pointermove', document, 380, 350);
		pointer('pointerup', document, 380, 350);
		place.click();

		expect(scroller.scrollLeft).toBe(420);
		expect(window.scrollTo).toHaveBeenCalledWith(window.scrollX, window.scrollY + 50);
		expect(clicked).not.toHaveBeenCalled();
	});

	it('klepnutí bez pohybu místo otevře', () => {
		const { area, place } = page();
		initDragPan(area, () => true);
		const clicked = vi.fn();
		place.addEventListener('click', clicked);

		pointer('pointerdown', place, 500, 400);
		pointer('pointermove', document, 502, 401);
		pointer('pointerup', document, 502, 401);
		place.click();

		expect(clicked).toHaveBeenCalledOnce();
		expect(window.scrollTo).not.toHaveBeenCalled();
	});

	it('nepřiblížený dokument a prst se netahají', () => {
		const { area, scroller, place } = page();
		let zoomed = false;
		initDragPan(area, () => zoomed);

		pointer('pointerdown', place, 500, 400);
		pointer('pointermove', document, 300, 400);
		pointer('pointerup', document, 300, 400);
		zoomed = true;
		pointer('pointerdown', place, 500, 400, 'touch');
		pointer('pointermove', document, 300, 400, 'touch');
		pointer('pointerup', document, 300, 400, 'touch');

		expect(scroller.scrollLeft).toBe(300);
	});
});
