// Tooltip se ukazuje sám podle [data-bs-toggle="tooltip"]: najetí myší se zpožděním, mezi sousedy hned, ne na dotyku
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let naja;

function pointer(type, el, pointerType = 'mouse', relatedTarget = null) {
	const event = new MouseEvent(type, { bubbles: true, relatedTarget });
	Object.defineProperty(event, 'pointerType', { value: pointerType });
	el.dispatchEvent(event);
}

// Vrátí, jestli Esc došel až k cíli (modal, panel)
function escape() {
	let reached = false;
	const listener = () => { reached = true; };
	document.body.addEventListener('keydown', listener);
	document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
	document.body.removeEventListener('keydown', listener);
	return reached;
}

const tooltips = () => document.querySelectorAll('.tooltip');

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	await import('../../assets/js/tooltip.js');
});

beforeEach(() => {
	vi.useFakeTimers();
	document.body.innerHTML = `
		<div id="snippet--grid">
			<span id="a" data-bs-toggle="tooltip" data-bs-title="<span class='tooltip-title'>211 · ZPMV</span>Zdravotní pojišťovna ministerstva vnitra ČR">211</span>
			<span id="b" data-bs-toggle="tooltip" data-bs-title="Hledat <kbd>Ctrl K</kbd>">b</span>
		</div>
		<span id="outside">x</span>`;
});

afterEach(() => {
	escape();
	vi.advanceTimersByTime(1000);
	vi.useRealTimers();
});

describe('tooltip', () => {
	it('ukáže se po 400 ms najetí myší', () => {
		pointer('pointerover', document.getElementById('a'));

		vi.advanceTimersByTime(399);
		expect(tooltips()).toHaveLength(0);

		vi.advanceTimersByTime(1);
		expect(tooltips()).toHaveLength(1);
		expect(document.querySelector('.tooltip-inner .tooltip-title').textContent).toBe('211 · ZPMV');
	});

	it('odjetí myší před zpožděním ho neukáže', () => {
		const a = document.getElementById('a');
		pointer('pointerover', a);
		vi.advanceTimersByTime(200);
		pointer('pointerout', a, 'mouse', document.getElementById('outside'));

		vi.advanceTimersByTime(1000);
		expect(tooltips()).toHaveLength(0);
	});

	it('na dotyku se neukáže', () => {
		pointer('pointerover', document.getElementById('a'), 'touch');

		vi.advanceTimersByTime(1000);
		expect(tooltips()).toHaveLength(0);
	});

	it('přejetí na sousední spouštěč do 300 ms ho ukáže hned', () => {
		const a = document.getElementById('a');
		const b = document.getElementById('b');
		pointer('pointerover', a);
		vi.advanceTimersByTime(400);

		pointer('pointerout', a, 'mouse', b);
		vi.advanceTimersByTime(100);
		pointer('pointerover', b);

		expect(tooltips()).toHaveLength(1);
		expect(document.querySelector('.tooltip-inner kbd').textContent).toBe('Ctrl K');
	});

	it('po delší pauze zase čeká 400 ms', () => {
		const a = document.getElementById('a');
		pointer('pointerover', a);
		vi.advanceTimersByTime(400);
		pointer('pointerout', a, 'mouse', document.getElementById('outside'));

		vi.advanceTimersByTime(500);
		pointer('pointerover', document.getElementById('b'));
		expect(tooltips()).toHaveLength(0);
	});

	it('Esc ho zavře a dál nepustí', () => {
		pointer('pointerover', document.getElementById('a'));
		vi.advanceTimersByTime(400);

		const reached = escape();

		expect(tooltips()).toHaveLength(0);
		expect(reached).toBe(false);
	});

	it('Esc bez otevřeného tooltipu propustí', () => {
		expect(escape()).toBe(true);
	});

	it('překreslený snippet nenechá viset bublinu', () => {
		const snippet = document.getElementById('snippet--grid');
		pointer('pointerover', document.getElementById('a'));
		vi.advanceTimersByTime(400);

		naja.snippetHandler.dispatchEvent(new CustomEvent('beforeUpdate', { detail: { snippet } }));

		expect(tooltips()).toHaveLength(0);
	});
});
