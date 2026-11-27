// Připnutí sloupce rychlé lišty (assets/js/quickbar.js): od zlomu md nepřipnutý panel zavře klik
// na stránku vedle něj, připnutý zůstane otevřený; klik do panelu, lišty nebo hlášky nezavírá nikdy.
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

let setQuickbarSource;

function quickbarHtml() {
	return `
		<main><button type="button" id="page">Stránka</button></main>
		<div class="flash-stack"><button type="button" id="flash">Beru na vědomí</button></div>
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel" data-quickbar-panel="todo" hidden>
			<button type="button" data-quickbar-column-pin data-pin="Připnout" data-unpin="Odepnout"></button>
			<div class="quickbar-panel-body" data-quickbar-list="todo" data-empty="Žádné úkoly."></div>
			<form class="quickbar-panel-form" data-quickbar-form="todo"><input type="text"></form>
		</section>
		<div class="quickbar" data-quickbar>
			<button type="button" data-quickbar-toggle="todo">Úkoly</button>
		</div>`;
}

const panel = () => document.querySelector('[data-quickbar-panel="todo"]');
const click = (selector) => document.querySelector(selector).click();

beforeAll(async () => {
	// Desktop: panel je sloupec vpravo (min-width: 768px), ne mobil
	window.matchMedia = vi.fn((query) => ({ matches: query.includes('min-width'), media: query, addEventListener() {}, removeEventListener() {} }));
	Element.prototype.scrollIntoView = vi.fn();
	await import('../../assets/js/_jquery-global.js');
	({ setQuickbarSource } = await import('../../assets/js/quickbar.js'));

	document.body.innerHTML = quickbarHtml();
	document.dispatchEvent(new Event('DOMContentLoaded'));
	setQuickbarSource({ todos: { list: () => [] } });
});

afterEach(() => {
	if (document.querySelector('[data-quickbar-column-pin]').getAttribute('aria-pressed') === 'true') {
		click('[data-quickbar-column-pin]');
	}
	if (!panel().hidden) {
		click('[data-quickbar-toggle="todo"]');
	}
});

describe('připnutí sloupce', () => {
	it('nepřipnutý panel zavře klik na stránku', () => {
		click('[data-quickbar-toggle="todo"]');
		expect(panel().hidden).toBe(false);

		click('[data-quickbar-list="todo"]');
		click('#flash');
		expect(panel().hidden).toBe(false);

		click('#page');
		expect(panel().hidden).toBe(true);
	});

	it('připnutý panel klik na stránku nezavře', () => {
		click('[data-quickbar-toggle="todo"]');
		click('[data-quickbar-column-pin]');
		expect(document.querySelector('[data-quickbar-column-pin]').getAttribute('aria-pressed')).toBe('true');

		click('#page');
		expect(panel().hidden).toBe(false);
	});
});
