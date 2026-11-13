// Výběr výsledků hledání v rychlé liště šipkami (assets/js/quickbar.js, README sekce 29): kurzor
// zůstává v poli, šipky posouvají výběr a Enter vybraný výsledek otevře stejně jako klik.
// Enter stisknutý před příchodem výsledků dopsaného textu otevře první výsledek nového hledání.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

const DEBOUNCE_MS = 200;

let setQuickbarSource;
let clicked;

// Výsledky podle dotazu: „nic“ nic nenajde, jinak dvě skupiny (3 + 2 odkazy), u dotazu „panel“
// je první výsledek položka otevírající panel Úkoly
function searchSource(query) {
	if (!query || query === 'nic') {
		return [];
	}
	const link = (i) => ({ label: `${query} ${i}`, href: `/r/${query}/${i}` });
	const first = query === 'panel' ? { label: 'Úkoly', panel: 'todo' } : link(0);
	return [
		{ label: 'Pacienti', items: [first, link(1), link(2)] },
		{ label: 'Objednávky', items: [link(3), link(4)] },
	];
}

// Minimální výřez z @quickbar.latte
function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel quickbar-panel--search" id="quickbar-panel-search" data-quickbar-panel="search" hidden>
			<div class="quickbar-panel-body" role="listbox" data-quickbar-results data-empty="Začněte psát." data-no-results="Nic pro „%query%“."></div>
		</section>
		<section class="quickbar-panel" data-quickbar-panel="todo" hidden>
			<form class="quickbar-panel-form" data-quickbar-form="todo"><input type="text"></form>
		</section>
		<div class="quickbar" data-quickbar>
			<label>
				<input type="search" role="combobox" aria-autocomplete="list" aria-expanded="false"
					data-placeholder-open="Hledat…" data-placeholder-closed="Hledat (Ctrl K)" data-quickbar-search>
				<kbd data-quickbar-shortcut>Ctrl K</kbd>
			</label>
		</div>`;
}

const input = () => document.querySelector('[data-quickbar-search]');
const selected = () => [...document.querySelectorAll('.quickbar-item--selected')];
const selectedIndex = () => {
	const items = selected();
	expect(items.length).toBeLessThanOrEqual(1);
	return items.length ? Number(items[0].dataset.quickbarResult) : -1;
};

// Napíše text do pole hledání (bez čekání na debounce)
function type(text) {
	input().value = text;
	input().dispatchEvent(new Event('input', { bubbles: true }));
}

// Nechá doběhnout debounce a promise zdroje dat
async function settle() {
	await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
	await vi.advanceTimersByTimeAsync(0);
}

async function search(text) {
	type(text);
	await settle();
}

function key(name, options = {}) {
	const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...options });
	input().dispatchEvent(event);
	return event;
}

beforeAll(async () => {
	// Modul čte při načtení matchMedia (mobilní zlom) a na konci $.nette; jsdom nemá scrollIntoView
	window.matchMedia = vi.fn((query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} }));
	Element.prototype.scrollIntoView = vi.fn();
	await import('../../assets/js/_jquery-global.js');
	({ setQuickbarSource } = await import('../../assets/js/quickbar.js'));

	document.body.innerHTML = quickbarHtml();
	document.dispatchEvent(new Event('DOMContentLoaded'));
	setQuickbarSource({ search: searchSource });

	// Klik na výsledek (i z Enteru) - zapamatovat cíl a nenechat jsdom navigovat
	document.addEventListener('click', (e) => {
		const result = e.target.closest('[data-quickbar-result]');
		if (result) {
			clicked.push(result.getAttribute('href') ?? `panel:${result.dataset.quickbarResult}`);
			if (result.matches('a[href]')) {
				e.preventDefault();
			}
		}
	});
});

beforeEach(() => {
	vi.useFakeTimers();
	clicked = [];
	Element.prototype.scrollIntoView.mockClear();
});

afterEach(async () => {
	// Zavřít lištu (Esc), další test začíná s prázdným hledáním
	key('Escape');
	await vi.runOnlyPendingTimersAsync();
	vi.useRealTimers();
});

describe('výběr výsledků hledání', () => {
	it('po vykreslení výsledků je vybraný první', async () => {
		await search('jan');

		expect(selectedIndex()).toBe(0);
		expect(selected()[0].getAttribute('aria-selected')).toBe('true');
		expect(document.querySelectorAll('[aria-selected="true"]')).toHaveLength(1);
		expect(input().getAttribute('aria-activedescendant')).toBe('quickbar-result-0');
	});

	it('šipka dolů posouvá výběr a kurzor zůstává v poli', async () => {
		await search('jan');

		const first = key('ArrowDown');
		key('ArrowDown');

		expect(first.defaultPrevented).toBe(true);
		expect(selectedIndex()).toBe(2);
		expect(input().getAttribute('aria-activedescendant')).toBe('quickbar-result-2');
		expect(document.activeElement).toBe(input());
		expect(Element.prototype.scrollIntoView).toHaveBeenCalled();
	});

	it('na krajích seznamu se výběr zastaví', async () => {
		await search('jan');

		key('ArrowUp');
		expect(selectedIndex()).toBe(0);

		for (let i = 0; i < 10; i++) {
			key('ArrowDown');
		}
		expect(selectedIndex()).toBe(4);
		expect(document.activeElement).toBe(input());
	});

	it('šipka s modifikátorem nebo při skládání znaků výběr nemění', async () => {
		await search('jan');

		expect(key('ArrowDown', { shiftKey: true }).defaultPrevented).toBe(false);
		key('ArrowDown', { ctrlKey: true });
		key('ArrowDown', { altKey: true });
		key('ArrowDown', { metaKey: true });
		key('ArrowDown', { isComposing: true });

		expect(selectedIndex()).toBe(0);
	});

	it('nové hledání vybere zase první výsledek', async () => {
		await search('jan');
		key('ArrowDown');
		key('ArrowDown');

		await search('jana');

		expect(selectedIndex()).toBe(0);
		expect(selected()[0].getAttribute('href')).toBe('/r/jana/0');
	});

	it('Enter otevře vybraný odkaz', async () => {
		await search('jan');
		key('ArrowDown');

		const enter = key('Enter');

		expect(enter.defaultPrevented).toBe(true);
		expect(clicked).toEqual(['/r/jan/1']);
	});

	it('Enter na položce s panelem otevře ten panel', async () => {
		await search('panel');

		key('Enter');

		expect(clicked).toEqual(['panel:0']);
		expect(document.querySelector('[data-quickbar-panel="todo"]').hidden).toBe(false);
		expect(document.querySelector('[data-quickbar-panel="search"]').hidden).toBe(true);
	});

	it('Enter před příchodem výsledků otevře první výsledek nového hledání', async () => {
		await search('jan');
		key('ArrowDown');

		type('jano');
		key('Enter');
		expect(clicked).toEqual([]);

		await settle();

		expect(clicked).toEqual(['/r/jano/0']);
	});

	it('další psaní čekající Enter zruší', async () => {
		await search('jan');

		type('jano');
		key('Enter');
		type('janou');
		await settle();

		expect(clicked).toEqual([]);
		expect(selectedIndex()).toBe(0);

		key('Enter');

		expect(clicked).toEqual(['/r/janou/0']);
	});

	it('pohyb myši nad výsledkem ho vybere bez scrollu, stejná pozice myši výběr nemění', async () => {
		await search('jan');
		Element.prototype.scrollIntoView.mockClear();
		const third = document.querySelector('[data-quickbar-result="2"]');

		third.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, screenX: 100, screenY: 200 }));

		expect(selectedIndex()).toBe(2);
		expect(input().getAttribute('aria-activedescendant')).toBe('quickbar-result-2');
		expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled();

		// Šipka posune seznam pod stojící myší - událost se stejnou pozicí výběr nevrátí
		key('ArrowUp');
		third.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, screenX: 100, screenY: 200 }));

		expect(selectedIndex()).toBe(1);
	});

	it('bez výsledků není nic vybrané a Enter nic neotevře', async () => {
		await search('jan');

		await search('nic');

		expect(selected()).toHaveLength(0);
		expect(input().hasAttribute('aria-activedescendant')).toBe(false);
		expect(document.querySelector('[data-quickbar-results]').textContent).toContain('Nic pro „nic“.');

		key('Enter');

		expect(clicked).toEqual([]);
	});

	it('zavření lišty odebere aria-activedescendant', async () => {
		await search('jan');
		expect(input().hasAttribute('aria-activedescendant')).toBe(true);

		key('Escape');

		expect(input().hasAttribute('aria-activedescendant')).toBe(false);
		expect(input().getAttribute('aria-expanded')).toBe('false');
	});
});
