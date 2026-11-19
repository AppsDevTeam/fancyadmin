// Načtení AI asistenta v rychlé liště (assets/js/quickbar.js, README sekce 29): panel ukáže stav
// načítání, při chybě hlášku a načte se znovu - dřív po selhání seznamu konverzací zůstal config
// nastavený bez připojeného widgetu a panel byl prázdný bez pole zprávy až do obnovení stránky.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let openQuickbarAi;
let responses;

// Minimální výřez z @quickbar.latte (panel AI)
function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel" data-quickbar-panel="ai" hidden>
			<div class="quickbar-ai" data-quickbar-ai="/ai?do=aiQuickbar">
				<aside class="quickbar-ai-sidebar">
					<input type="text" data-quickbar-ai-query>
					<div data-quickbar-ai-groups data-empty="" data-no-results="" data-today="" data-yesterday="" data-week="" data-older=""></div>
				</aside>
				<div class="quickbar-ai-list" data-quickbar-ai-list data-empty="Žádné konverzace" data-untitled="" data-unread=""></div>
				<a data-quickbar-ai-all hidden></a>
				<div class="quickbar-ai-chat" data-quickbar-ai-chat data-loading="Načítám AI asistenta…" data-unavailable="AI asistent teď není dostupný."></div>
			</div>
		</section>
		<div class="quickbar" data-quickbar>
			<input type="search" data-quickbar-search data-placeholder-open="" data-placeholder-closed="">
			<kbd data-quickbar-shortcut>Ctrl K</kbd>
		</div>`;
}

const chat = () => document.querySelector('[data-quickbar-ai-chat]');
const widget = () => chat().querySelector('.quickbar-ai-widget');

// Odpověď podle URL: funkce vrací payload, nebo vyhodí (výpadek sítě)
function respond(url) {
	const handler = Object.entries(responses).find(([prefix]) => url.startsWith(prefix))?.[1];
	if (!handler) {
		return Promise.resolve(new Response('{}', { status: 200 }));
	}
	return Promise.resolve().then(() => new Response(JSON.stringify(handler()), { status: 200 }));
}

const config = { chat: { urls: { conversations: '/ai/conversations' } }, historyUrl: null };

beforeAll(async () => {
	window.matchMedia = vi.fn((query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} }));
	Element.prototype.scrollIntoView = vi.fn();
	globalThis.fetch = vi.fn((url) => respond(String(url)));
	await import('../../assets/js/_jquery-global.js');
	({ openQuickbarAi } = await import('../../assets/js/quickbar.js'));

	document.body.innerHTML = quickbarHtml();
	document.dispatchEvent(new Event('DOMContentLoaded'));
});

beforeEach(() => {
	responses = {
		'/ai?do=aiQuickbar': () => config,
		'/ai/conversations': () => ({ conversations: [] }),
	};
});

afterEach(() => {
	fetch.mockClear();
});

async function settle() {
	for (let i = 0; i < 5; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0));
	}
}

describe('načtení AI asistenta', () => {
	it('po chybě seznamu konverzací ukáže hlášku a po návratu sítě připojí widget', async () => {
		responses['/ai/conversations'] = () => {
			throw new TypeError('Failed to fetch');
		};
		openQuickbarAi();
		expect(chat().textContent).toContain('Načítám AI asistenta…');
		await settle();

		expect(widget()).toBeNull();
		expect(chat().textContent).toContain('AI asistent teď není dostupný.');

		responses['/ai/conversations'] = () => ({ conversations: [] });
		window.dispatchEvent(new Event('online'));
		await settle();

		const aiCalls = fetch.mock.calls.filter(([url]) => String(url).startsWith('/ai'));
		expect(aiCalls.map(([url]) => url)).toContain('/ai?do=aiQuickbar');
		// Časový limit, jinak požadavek po uspání počítače visí minuty
		aiCalls.forEach(([, options]) => expect(options.signal).toBeInstanceOf(AbortSignal));
		expect(widget()).not.toBeNull();
	});
});
