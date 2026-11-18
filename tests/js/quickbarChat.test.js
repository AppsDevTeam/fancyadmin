// Chat v rychlé liště - téma konverzace, nabídka plusu, výběr zpráv a výběr položky s hledáním
// (assets/js/quickbar.js, README sekce 29). Co zdroj neumí, lišta neukáže.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

const DEBOUNCE_MS = 200;

let setQuickbarSource;
let pickInQuickbar;
let source;
let topic;
let messages;
let calls;

// Minimální výřez z @quickbar.latte (panel Chat)
function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel quickbar-panel--chat" data-quickbar-panel="chat" hidden>
			<div class="quickbar-chat-current" data-quickbar-chat-current data-back="Zpět" data-colleague="Kolega" data-group="Skupina"></div>
			<div class="quickbar-chat-list">
				<input type="text" data-quickbar-chat-query>
				<div class="quickbar-panel-body quickbar-chats" data-quickbar-chats data-empty="Nic" data-no-results="Nic pro %query%"></div>
			</div>
			<div class="quickbar-thread" data-quickbar-thread></div>
			<div class="quickbar-chat-topic" data-quickbar-chat-topic data-label="Řešíme" data-clear="Ukončit" hidden></div>
			<div class="quickbar-chat-selection" data-quickbar-chat-selection data-count="Vybráno: %count%" data-cancel="Zrušit" data-hint="Klikněte na zprávy" hidden></div>
			<div class="quickbar-chat-menu" data-quickbar-chat-menu data-select="Vybrat zprávy" hidden></div>
			<div class="quickbar-picker" data-quickbar-picker data-close="Zpět" data-empty="Nic nenalezeno" data-short="Aspoň %count% znaky" data-suggestions="Návrhy" hidden></div>
			<form class="quickbar-panel-form quickbar-chat-form" data-quickbar-form="chat">
				<button type="button" data-quickbar-chat-plus hidden></button>
				<input type="text">
				<button type="submit"></button>
			</form>
		</section>
		<div class="quickbar" data-quickbar>
			<button type="button" data-quickbar-toggle="chat"></button>
			<input type="search" data-quickbar-search data-placeholder-open="" data-placeholder-closed="">
		</div>`;
}

const PATIENTS = [
	{ id: 1, label: 'Jan Novák', meta: 'nar. 1. 2. 1980' },
	{ id: 2, label: 'Eva Svobodová' },
];

function fullSource() {
	return {
		chats: {
			list: () => [{ id: 7, name: 'Eva Recepční', unread: 0 }],
			messages: () => ({ messages: messages.map((m) => ({ ...m })), topic }),
			send: (chatId, text) => ({ id: 99, mine: true, text, time: '10:00' }),
			read: () => {},
			menu: () => [{ id: 'patient', icon: 'fa-regular fa-user-tag', label: 'Řešíme pacienta' }],
			menuAction: async (chatId, id) => {
				calls.push(['menuAction', chatId, id]);
				const item = await pickInQuickbar({
					title: 'O kom píšete?',
					suggestions: PATIENTS,
					search: (query) => PATIENTS.filter((p) => p.label.toLowerCase().includes(query.toLowerCase())),
				});
				if (item) {
					topic = { label: item.label, href: `/patient/${item.id}` };
				}
			},
			clearTopic: (chatId) => {
				calls.push(['clearTopic', chatId]);
				topic = null;
			},
			selectionActions: () => [{ id: 'tag', label: 'K pacientovi' }, { id: 'untag', label: 'Odebrat štítek' }],
			selectionAction: (chatId, id, ids) => {
				calls.push(['selectionAction', chatId, id, ids]);
				return id === 'cancel' ? false : undefined;
			},
		},
	};
}

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function click(selector) {
	const el = typeof selector === 'string' ? $(selector) : selector;
	el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

function key(name, target = document.activeElement ?? document.body) {
	const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true });
	target.dispatchEvent(event);
	return event;
}

async function settle(ms = 0) {
	await vi.advanceTimersByTimeAsync(ms);
	await vi.advanceTimersByTimeAsync(0);
}

async function openThread() {
	click('[data-quickbar-toggle="chat"]');
	await settle();
	click('[data-quickbar-chat="7"]');
	await settle();
}

beforeAll(async () => {
	window.matchMedia = vi.fn((query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} }));
	Element.prototype.scrollIntoView = vi.fn();
	await import('../../assets/js/_jquery-global.js');
	({ setQuickbarSource, pickInQuickbar } = await import('../../assets/js/quickbar.js'));

	document.body.innerHTML = quickbarHtml();
	document.dispatchEvent(new Event('DOMContentLoaded'));
	// Odkazy štítků v jsdom nenavigují
	document.addEventListener('click', (e) => {
		if (e.target.closest('a[href]')) {
			e.preventDefault();
		}
	});
});

beforeEach(() => {
	vi.useFakeTimers();
	topic = null;
	calls = [];
	messages = [
		{ id: 1, mine: false, text: 'Dobrý den', time: '9:00' },
		{ id: 2, mine: true, text: 'Objednat kontrolu', time: '9:01', tag: { text: 'Jan Novák', href: '/patient/1' } },
		{ id: 3, mine: false, text: 'Na úterý?', time: '9:02', tag: { text: 'Jan Novák', href: '/patient/1' } },
	];
	source = fullSource();
	setQuickbarSource(source);
});

afterEach(async () => {
	// Esc postupně zavře výběr, nabídku, vlákno i panel
	for (let i = 0; i < 4; i++) {
		key('Escape', document.body);
	}
	await vi.runOnlyPendingTimersAsync();
	vi.useRealTimers();
});

describe('zdroj bez rozšíření', () => {
	it('plus ani téma neukáže a zprávy bere jako pole', async () => {
		setQuickbarSource({ chats: { list: () => [{ id: 7, name: 'Eva', unread: 0 }], messages: () => [{ mine: true, text: 'Ahoj', time: '9:00' }] } });
		await openThread();

		expect($('[data-quickbar-chat-plus]').hidden).toBe(true);
		expect($('[data-quickbar-chat-topic]').hidden).toBe(true);
		expect($('[data-quickbar-thread]').textContent).toContain('Ahoj');
	});
});

describe('štítky a téma', () => {
	it('řada zpráv se stejným štítkem je jeden blok se štítkem nahoře', async () => {
		messages.push({ id: 4, mine: true, text: 'Po tématu', time: '9:03' });
		messages.push({ id: 5, mine: true, text: 'Jiný pacient', time: '9:04', tag: { text: 'Eva Svobodová', href: '/patient/2' } });
		await openThread();

		const groups = $$('[data-quickbar-thread] .quickbar-message-group');
		expect(groups).toHaveLength(2);
		expect(groups[0].querySelectorAll('.quickbar-message')).toHaveLength(2);
		expect(groups[0].textContent).toContain('Objednat kontrolu');
		expect(groups[0].textContent).toContain('Na úterý?');
		// Zprávy bez štítku jsou mimo bloky
		expect($('[data-quickbar-thread] > .quickbar-message').textContent).toContain('Dobrý den');
		expect(groups[1].textContent).toContain('Jiný pacient');

		const tags = $$('[data-quickbar-thread] .quickbar-message-tag');
		expect(tags).toHaveLength(2);
		expect(tags[0].getAttribute('href')).toBe('/patient/1');
		expect(tags[0].textContent).toContain('Jan Novák');
	});

	it('téma je v pruhu nad polem a Ukončit ho zruší', async () => {
		topic = { label: 'Jan Novák', href: '/patient/1' };
		await openThread();

		expect($('[data-quickbar-chat-topic]').hidden).toBe(false);
		expect($('.quickbar-chat-topic-name').textContent).toBe('Jan Novák');

		click('[data-quickbar-chat-topic-clear]');
		await settle();

		expect(calls).toContainEqual(['clearTopic', 7]);
		expect($('[data-quickbar-chat-topic]').hidden).toBe(true);
	});
});

describe('nabídka plusu a výběr položky', () => {
	it('plus otevře nabídku s položkami zdroje a Vybrat zprávy', async () => {
		await openThread();
		expect($('[data-quickbar-chat-plus]').hidden).toBe(false);

		click('[data-quickbar-chat-plus]');
		await settle();

		expect($('[data-quickbar-chat-menu]').hidden).toBe(false);
		expect($$('[data-quickbar-chat-menu-item]').map((el) => el.dataset.quickbarChatMenuItem)).toEqual(['patient', '__select']);
		expect($('[data-quickbar-chat-plus]').getAttribute('aria-expanded')).toBe('true');

		key('Escape');
		expect($('[data-quickbar-chat-menu]').hidden).toBe(true);
	});

	it('výběr z návrhů nastaví téma', async () => {
		await openThread();
		click('[data-quickbar-chat-plus]');
		await settle();
		click('[data-quickbar-chat-menu-item="patient"]');
		await settle();

		expect($('[data-quickbar-picker]').hidden).toBe(false);
		expect($('[data-quickbar-picker]').textContent).toContain('Návrhy');
		expect($$('[data-quickbar-picker-item]')).toHaveLength(2);

		click($$('[data-quickbar-picker-item]')[0]);
		await settle();

		expect($('[data-quickbar-picker]').hidden).toBe(true);
		expect(calls).toContainEqual(['menuAction', 7, 'patient']);
		expect($('.quickbar-chat-topic-name').textContent).toBe('Jan Novák');
	});

	it('hledání po prodlevě, šipky a Enter vyberou výsledek', async () => {
		await openThread();
		click('[data-quickbar-chat-plus]');
		await settle();
		click('[data-quickbar-chat-menu-item="patient"]');
		await settle();

		const query = $('[data-quickbar-picker-query]');
		expect(document.activeElement).toBe(query);
		// Krátký text filtruje návrhy, bez shody radí psát dál
		query.value = 'j';
		query.dispatchEvent(new Event('input', { bubbles: true }));
		expect($$('[data-quickbar-picker-item]').map((el) => el.textContent.trim())).toEqual([expect.stringContaining('Jan Novák')]);
		query.value = 'x';
		query.dispatchEvent(new Event('input', { bubbles: true }));
		expect($('[data-quickbar-picker]').textContent).toContain('Aspoň 2 znaky');

		query.value = 'ov';
		query.dispatchEvent(new Event('input', { bubbles: true }));
		await settle(DEBOUNCE_MS);
		expect($$('[data-quickbar-picker-item]')).toHaveLength(2);

		key('ArrowDown', query);
		key('Enter', query);
		await settle();

		expect($('.quickbar-chat-topic-name').textContent).toBe('Eva Svobodová');
	});

	it('Enter stisknutý před příchodem výsledků vybere první výsledek', async () => {
		await openThread();
		click('[data-quickbar-chat-plus]');
		await settle();
		click('[data-quickbar-chat-menu-item="patient"]');
		await settle();

		const query = $('[data-quickbar-picker-query]');
		query.value = 'Svobod';
		query.dispatchEvent(new Event('input', { bubbles: true }));
		key('Enter', query);
		await settle();
		expect($('[data-quickbar-picker]').hidden).toBe(false);

		await settle(DEBOUNCE_MS);

		expect($('[data-quickbar-picker]').hidden).toBe(true);
		expect($('.quickbar-chat-topic-name').textContent).toBe('Eva Svobodová');
	});

	it('Esc výběr položky zavře bez tématu', async () => {
		await openThread();
		click('[data-quickbar-chat-plus]');
		await settle();
		click('[data-quickbar-chat-menu-item="patient"]');
		await settle();

		key('Escape', $('[data-quickbar-picker-query]'));
		await settle();

		expect($('[data-quickbar-picker]').hidden).toBe(true);
		expect($('[data-quickbar-chat-topic]').hidden).toBe(true);
		expect($('[data-quickbar-panel="chat"]').hidden).toBe(false);
	});
});

describe('výběr zpráv', () => {
	async function startSelecting() {
		await openThread();
		click('[data-quickbar-chat-plus]');
		await settle();
		click('[data-quickbar-chat-menu-item="__select"]');
		await settle();
	}

	it('místo pole zprávy je lišta výběru a zprávy jdou vybrat', async () => {
		await startSelecting();

		expect($('[data-quickbar-form="chat"]').hidden).toBe(true);
		expect($('[data-quickbar-chat-selection]').textContent).toContain('Klikněte na zprávy');
		expect($('[data-quickbar-selection-action="tag"]').disabled).toBe(true);
		// Štítek při výběru nenaviguje
		expect($('[data-quickbar-thread] a.quickbar-message-tag')).toBeNull();

		click('[data-quickbar-message="1"]');
		key(' ', $('[data-quickbar-message="3"]'));

		expect($('[data-quickbar-chat-selection]').textContent).toContain('Vybráno: 2');
		expect($('[data-quickbar-message="1"]').getAttribute('aria-checked')).toBe('true');
		expect($('[data-quickbar-selection-action="tag"]').disabled).toBe(false);
	});

	it('akce dostane vybrané zprávy a výběr skončí', async () => {
		await startSelecting();
		click('[data-quickbar-message="1"]');
		click('[data-quickbar-message="2"]');

		click('[data-quickbar-selection-action="tag"]');
		await settle();

		expect(calls).toContainEqual(['selectionAction', 7, 'tag', ['1', '2']]);
		expect($('[data-quickbar-chat-selection]').hidden).toBe(true);
		expect($('[data-quickbar-form="chat"]').hidden).toBe(false);
	});

	it('zrušená akce výběr nechá', async () => {
		source.chats.selectionActions = () => [{ id: 'cancel', label: 'Zkusit' }];
		await startSelecting();
		click('[data-quickbar-message="1"]');

		click('[data-quickbar-selection-action="cancel"]');
		await settle();

		expect($('[data-quickbar-chat-selection]').hidden).toBe(false);
		expect($('[data-quickbar-chat-selection]').textContent).toContain('Vybráno: 1');
	});

	it('Zrušit výběr ukončí', async () => {
		await startSelecting();
		click('[data-quickbar-message="1"]');

		click('[data-quickbar-selection-cancel]');

		expect($('[data-quickbar-chat-selection]').hidden).toBe(true);
		expect($('[data-quickbar-message="1"]')).toBeNull();
	});
});
