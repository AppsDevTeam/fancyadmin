// Chat v rychlé liště - téma konverzace, nabídka plusu, výběr zpráv, výběr položky s hledáním,
// kanály, vlákna, připnuté zprávy, připomenutí, přílohy a ztlumení
// (assets/js/quickbar.js, README sekce 29). Co zdroj neumí, lišta neukáže.
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

const DEBOUNCE_MS = 200;

let setQuickbarSource;
let pickInQuickbar;
let refreshQuickbarChat;
let source;
let topic;
let messages;
let calls;

// Minimální výřez z @quickbar.latte (panel Chat)
function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel quickbar-panel--chat" data-quickbar-panel="chat" hidden>
			<div class="quickbar-chat-current" data-quickbar-chat-current data-back="Zpět" data-colleague="Kolega" data-group="Skupina"
				data-channel="Kanál" data-private-channel="Soukromý kanál" data-thread="Vlákno" data-thread-back="Zpět do konverzace"
				data-follow="Sledovat" data-unfollow="Přestat sledovat"
				data-members-one="%count% člen" data-members-few="%count% členové" data-members-many="%count% členů" data-muted="Ztlumeno"></div>
			<div class="quickbar-chat-list">
				<input type="text" data-quickbar-chat-query>
				<div data-quickbar-chat-tools data-new="Nový kanál" data-browse="Procházet kanály" hidden></div>
				<div class="quickbar-panel-body quickbar-chats" data-quickbar-chats data-empty="Nic" data-no-results="Nic pro %query%"
					data-threads="Vlákna" data-threads-hint="Odpovědi ve vláknech, která sledujete"
					data-reminders="Připomenout později" data-reminders-hint="Zprávy, ke kterým se máte vrátit" data-muted="Ztlumeno"></div>
				<div data-quickbar-channels data-new="Nový kanál" data-browse="Procházet kanály" data-back="Zpět" data-name="Název" data-private="Soukromý"
					data-members="Členové" data-members-search="Hledat kolegu" data-create="Vytvořit kanál" data-colleagues-empty="Nikdo" data-empty="Žádné kanály"
					data-members-count="Členů: %count%" data-join="Připojit" data-open="Otevřít"
					data-threads="Vlákna" data-threads-empty="Zatím nesledujete žádné vlákno s odpověďmi."
					data-reminders="Připomenout později" data-reminders-empty="Nemáte žádné připomínky." data-reminder-done="Hotovo" hidden></div>
			</div>
			<div class="quickbar-chat-members" id="quickbar-chat-members" data-quickbar-chat-members aria-label="Členové konverzace" data-you="(vy)" hidden></div>
			<div data-quickbar-chat-pinned data-label="Připnuté (%count%)" hidden></div>
			<div data-quickbar-chat-news data-one="Nové odpovědi ve vlákně" data-few="Nové odpovědi ve %count% vláknech"
				data-many="Nové odpovědi v %count% vláknech" data-open="Zobrazit" hidden></div>
			<div class="quickbar-thread" data-quickbar-thread data-reply="Odpovědět ve vlákně" data-replies-one="%count% odpověď" data-replies-few="%count% odpovědi"
				data-replies-many="%count% odpovědí" data-last-reply="poslední %time%" data-pin="Připnout" data-unpin="Odepnout" data-remind="Připomenout později"
				data-remind-minutes="Za 20 minut" data-remind-hour="Za hodinu" data-remind-tomorrow="Zítra v 8:00" data-remind-custom="Vlastní čas…" data-remind-set="Nastavit"
				data-new-replies-one="%count% nová odpověď" data-new-replies-few="%count% nové odpovědi" data-new-replies-many="%count% nových odpovědí"></div>
			<div class="quickbar-chat-topic" data-quickbar-chat-topic data-label="Řešíme" data-clear="Ukončit" hidden></div>
			<div class="quickbar-chat-selection" data-quickbar-chat-selection data-count="Vybráno: %count%" data-cancel="Zrušit" data-hint="Klikněte na zprávy" hidden></div>
			<div class="quickbar-chat-menu" data-quickbar-chat-menu data-select="Vybrat zprávy" hidden></div>
			<div class="quickbar-picker" data-quickbar-picker data-close="Zpět" data-empty="Nic nenalezeno" data-short="Aspoň %count% znaky" data-suggestions="Návrhy" hidden></div>
			<div data-quickbar-chat-files data-remove="Odebrat" hidden></div>
			<form class="quickbar-panel-form quickbar-chat-form" data-quickbar-form="chat">
				<button type="button" data-quickbar-chat-plus hidden></button>
				<button type="button" data-quickbar-chat-attach hidden></button>
				<input type="text">
				<input type="file" multiple hidden data-quickbar-chat-file>
				<button type="submit"></button>
			</form>
		</section>
		<div class="quickbar" data-quickbar>
			<button type="button" data-quickbar-toggle="chat"><span data-quickbar-count="chat" hidden></span></button>
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
	({ setQuickbarSource, pickInQuickbar, refreshQuickbarChat } = await import('../../assets/js/quickbar.js'));

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
	// Esc postupně zavře výběr, nabídku, připomenutí, vlákno odpovědí, konverzaci, kanály i panel
	for (let i = 0; i < 6; i++) {
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

// Zdroj se vším, co lišta pro kanály, vlákna, připnuté zprávy, připomenutí a přílohy umí
function slackSource() {
	const base = fullSource().chats;
	let chats = [
		{ id: 7, name: 'Eva Recepční', unread: 0 },
		{ id: 20, name: 'recepce', kind: 'channel', unread: 0 },
	];
	return {
		chats: {
			...base,
			attachments: true,
			list: () => chats,
			messages: () => ({
				messages: [
					{ id: 1, mine: false, author: 'Eva Recepční', text: 'Dobrý den', time: '9:00', canPin: true, pinned: true, replyCount: 2, replyAuthors: ['JN', 'ER'], lastReplyTime: '9:30' },
					{ id: 2, mine: true, text: '', time: '9:01', canPin: false, attachments: [
						{ name: 'snimek.png', url: '/files/1', size: 2048, image: true },
						{ name: 'zprava.pdf', url: '/files/2', size: 1536000 },
					] },
					{ id: 3, mine: false, system: true, text: 'Jan se připojil', time: '9:02' },
				],
				pinned: [{ id: 1, text: 'Dobrý den', author: 'Eva Recepční', time: '9:00' }],
			}),
			send: (chatId, text, options) => {
				calls.push(['send', chatId, text, options]);
				return { id: 100, mine: true, text, time: '10:00' };
			},
			browseChannels: () => [
				{ id: 20, name: 'recepce', private: false, members: 4, joined: true },
				{ id: 21, name: 'sestry', private: false, members: 3, joined: false },
			],
			joinChannel: (id) => {
				calls.push(['joinChannel', id]);
				const chat = { id, name: 'sestry', kind: 'channel', unread: 0 };
				chats = [...chats, chat];
				return chat;
			},
			colleagues: () => [{ id: 5, name: 'Jan Novák' }, { id: 6, name: 'Eva Svobodová' }],
			createChannel: (data) => {
				calls.push(['createChannel', data]);
				return { id: 30, name: data.name, kind: 'channel', private: data.private, unread: 0 };
			},
			thread: (chatId, messageId) => {
				calls.push(['thread', chatId, messageId]);
				return {
					parent: { id: messageId, mine: false, author: 'Eva Recepční', text: 'Dobrý den', time: '9:00' },
					replies: [{ id: 11, mine: false, author: 'Jan Novák', text: 'Ahoj', time: '9:10' }],
				};
			},
			pin: (chatId, messageId, pinned) => calls.push(['pin', chatId, messageId, pinned]),
			remind: (chatId, messageId, when) => {
				calls.push(['remind', chatId, messageId, when]);
				return { label: 'Připomenu zítra v 8:00' };
			},
		},
	};
}

async function openChatPanel() {
	click('[data-quickbar-toggle="chat"]');
	await settle();
}

function submitChat() {
	$('[data-quickbar-form="chat"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}

function message(id) {
	return $(`[data-quickbar-thread] [data-quickbar-message-id="${id}"]`);
}

describe('kanály, vlákna, připnuté zprávy, připomenutí a přílohy', () => {
	beforeEach(() => {
		source = slackSource();
		setQuickbarSource(source);
	});

	it('bez nových metod zdroje nic z toho neukáže', async () => {
		setQuickbarSource(fullSource());
		await openChatPanel();
		expect($('[data-quickbar-chat-tools]').hidden).toBe(true);
		click('[data-quickbar-chat="7"]');
		await settle();

		expect($('[data-quickbar-chat-pinned]').hidden).toBe(true);
		expect($('[data-quickbar-chat-attach]').hidden).toBe(true);
		expect($$('.quickbar-message-actions')).toHaveLength(0);
		expect($$('[data-quickbar-replies]')).toHaveLength(0);
	});

	it('kanál má avatar # a zámek u soukromého', async () => {
		source.chats.list = () => [{ id: 20, name: 'vedení', kind: 'channel', private: true, unread: 0 }];
		await openChatPanel();

		const row = $('[data-quickbar-chat="20"]');
		expect(row.querySelector('.quickbar-chat-avatar--channel').textContent).toBe('#');
		expect(row.querySelector('.fa-lock')).not.toBeNull();
	});

	it('nový kanál pošle název, soukromí a vybrané členy a kanál otevře', async () => {
		await openChatPanel();
		click('[data-quickbar-channel-new]');
		await settle();

		expect($('[data-quickbar-chats]').hidden).toBe(true);
		$('[data-quickbar-channel-name]').value = 'Ortodoncie';
		$('[data-quickbar-channel-private]').checked = true;
		const query = $('[data-quickbar-channel-query]');
		query.value = 'svob';
		query.dispatchEvent(new Event('input', { bubbles: true }));
		expect($$('[data-quickbar-channel-member]')).toHaveLength(1);
		const member = $('[data-quickbar-channel-member="6"]');
		member.checked = true;
		member.dispatchEvent(new Event('change', { bubbles: true }));
		$('[data-quickbar-channel-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
		await settle();

		expect(calls).toContainEqual(['createChannel', { name: 'Ortodoncie', private: true, memberIds: [6] }]);
		expect($('[data-quickbar-chat-current]').textContent).toContain('Ortodoncie');
		expect($('[data-quickbar-chat-current]').textContent).toContain('Soukromý kanál');
	});

	it('procházení kanálů: Připojit zavolá joinChannel a kanál otevře, Otevřít otevře připojený', async () => {
		await openChatPanel();
		click('[data-quickbar-channel-browse]');
		await settle();

		expect($('[data-quickbar-channel-open="20"]').textContent).toContain('Otevřít');
		click('[data-quickbar-channel-join="21"]');
		await settle();

		expect(calls).toContainEqual(['joinChannel', 21]);
		expect($('.quickbar-panel--chat-thread')).not.toBeNull();
		expect($('[data-quickbar-chat-current]').textContent).toContain('sestry');

		click('[data-quickbar-chat-back]');
		await settle();
		click('[data-quickbar-channel-browse]');
		await settle();
		click('[data-quickbar-channel-open="20"]');
		await settle();
		expect($('[data-quickbar-chat-current]').textContent).toContain('recepce');
	});

	it('souhrn odpovědí otevře vlákno a odpověď jde s parentId', async () => {
		await openThread();
		const summary = $('[data-quickbar-replies="1"]');
		expect(summary.textContent).toContain('2 odpovědi');
		expect(summary.textContent).toContain('poslední 9:30');
		expect(summary.textContent).toContain('JN');

		click(summary);
		await settle();
		expect(calls).toContainEqual(['thread', 7, 1]);
		expect($('[data-quickbar-chat-current]').textContent).toContain('Vlákno');
		expect(message(11).textContent).toContain('Ahoj');

		$('[data-quickbar-form="chat"] input[type="text"]').value = 'Hotovo';
		submitChat();
		await settle();
		expect(calls).toContainEqual(['send', 7, 'Hotovo', { parentId: 1 }]);
		expect(message(100).textContent).toContain('Hotovo');

		// Esc vrací z vlákna do konverzace
		key('Escape', document.body);
		await settle();
		expect($('[data-quickbar-replies="1"]')).not.toBeNull();
	});

	it('Odpovědět ve vlákně v akcích zprávy otevře vlákno', async () => {
		await openThread();
		click('[data-quickbar-message-more="1"]');
		click('[data-quickbar-reply="1"]');
		await settle();

		expect(calls).toContainEqual(['thread', 7, 1]);
		expect($('.quickbar-remind-menu')).toBeNull();
		click('[data-quickbar-message-more="11"]');
		expect($('[data-quickbar-reply="11"]')).toBeNull();
	});

	it('Připnout/Odepnout jen u zprávy s canPin a volá pin', async () => {
		await openThread();
		click('[data-quickbar-message-more="2"]');
		expect($('[data-quickbar-pin="2"]')).toBeNull();
		expect($('[data-quickbar-message-more="3"]')).toBeNull();
		click('[data-quickbar-message-more="1"]');
		expect($('[data-quickbar-pin="2"]')).toBeNull();
		expect($('[data-quickbar-pin="1"]').textContent).toContain('Odepnout');

		click('[data-quickbar-pin="1"]');
		await settle();

		expect(calls).toContainEqual(['pin', 7, 1, false]);
	});

	it('připnuté jsou sbalené v pruhu a klik na položku zvýrazní zprávu', async () => {
		await openThread();
		const $pinned = $('[data-quickbar-chat-pinned]');
		expect($pinned.hidden).toBe(false);
		expect($pinned.textContent).toContain('Připnuté (1)');
		expect($('[data-quickbar-pinned-item]')).toBeNull();

		click('[data-quickbar-pinned-toggle]');
		click('[data-quickbar-pinned-item="1"]');

		expect(message(1).classList.contains('quickbar-message--highlight')).toBe(true);
		expect(Element.prototype.scrollIntoView).toHaveBeenCalled();
		await settle(2000);
		expect(message(1).classList.contains('quickbar-message--highlight')).toBe(false);
	});

	it('připomenutí nabídne časy, pošle zvolený a ukáže potvrzení', async () => {
		await openThread();
		click('[data-quickbar-message-more="1"]');
		click('[data-quickbar-remind="1"]');
		const options = $$('[data-quickbar-remind-when]').map((el) => el.dataset.quickbarRemindWhen);
		expect(options).toEqual(['20m', '1h', 'tomorrow', 'custom']);

		click('[data-quickbar-remind-when="tomorrow"]');
		await settle();

		expect(calls).toContainEqual(['remind', 7, 1, 'tomorrow']);
		expect(message(1).querySelector('.quickbar-message-notice').textContent).toContain('Připomenu zítra v 8:00');
		expect($('.quickbar-remind-menu')).toBeNull();
	});

	it('vlastní čas připomenutí pošle jako ISO', async () => {
		await openThread();
		click('[data-quickbar-message-more="1"]');
		click('[data-quickbar-remind="1"]');
		click('[data-quickbar-remind-when="custom"]');
		$('[data-quickbar-remind-at]').value = '2026-11-21T09:30';
		click('[data-quickbar-remind-set]');
		await settle();

		expect(calls).toContainEqual(['remind', 7, 1, new Date('2026-11-21T09:30').toISOString()]);
	});

	it('systémová zpráva akce nemá, přílohy jsou náhled a soubor s velikostí', async () => {
		await openThread();
		expect(message(3).querySelector('.quickbar-message-actions')).toBeNull();

		const files = message(2);
		expect(files.querySelector('.quickbar-bubble')).toBeNull();
		const image = files.querySelector('.quickbar-file-image');
		expect(image.getAttribute('href')).toBe('/files/1');
		expect(image.getAttribute('target')).toBe('_blank');
		expect(image.querySelector('img').getAttribute('src')).toBe('/files/1');
		const file = files.querySelector('.quickbar-file');
		expect(file.textContent).toContain('zprava.pdf');
		expect(file.textContent).toContain('1,5 MB');
		expect(file.getAttribute('target')).toBe('_blank');
		expect(message(1).querySelector('.quickbar-message-author').textContent).toBe('Eva Recepční');
	});

	it('vybrané soubory jsou čipy a odejdou i bez textu v options.files', async () => {
		await openThread();
		expect($('[data-quickbar-chat-attach]').hidden).toBe(false);

		const input = $('[data-quickbar-chat-file]');
		const report = new File(['x'], 'report.pdf', { type: 'application/pdf' });
		const photo = new File(['y'], 'foto.jpg', { type: 'image/jpeg' });
		Object.defineProperty(input, 'files', { value: [report, photo], configurable: true });
		input.dispatchEvent(new Event('change', { bubbles: true }));

		expect($$('.quickbar-chat-file')).toHaveLength(2);
		click('[data-quickbar-chat-file-remove="1"]');
		expect($$('.quickbar-chat-file')).toHaveLength(1);

		submitChat();
		await settle();

		expect(calls).toContainEqual(['send', 7, '', { files: [report] }]);
		expect($('[data-quickbar-chat-files]').hidden).toBe(true);
	});

	it('během nahrávání je odeslání zakázané', async () => {
		let finish;
		source.chats.send = (chatId, text, options) => {
			calls.push(['send', chatId, text, options]);
			return new Promise((resolve) => {
				finish = resolve;
			});
		};
		await openThread();
		const input = $('[data-quickbar-chat-file]');
		Object.defineProperty(input, 'files', { value: [new File(['x'], 'a.pdf')], configurable: true });
		input.dispatchEvent(new Event('change', { bubbles: true }));

		submitChat();
		await settle();
		expect($('[data-quickbar-form="chat"] [type="submit"]').disabled).toBe(true);
		submitChat();
		await settle();
		expect(calls.filter((c) => c[0] === 'send')).toHaveLength(1);

		finish({ id: 101, mine: true, text: '', time: '10:00' });
		await settle();
		expect($('[data-quickbar-form="chat"] [type="submit"]').disabled).toBe(false);
	});

	it('obnova chatu načte i otevřené vlákno', async () => {
		await openThread();
		click('[data-quickbar-replies="1"]');
		await settle();
		calls = [];

		refreshQuickbarChat();
		await settle();

		expect(calls).toContainEqual(['thread', 7, 1]);
	});

	describe('členové kanálu v hlavičce', () => {
		const MEMBERS = [
			{ id: 1, name: 'Eva Recepční', initials: 'ER' },
			{ id: 2, name: 'Jan Novák', me: true },
			{ id: 3, name: 'Petra Malá' },
		];

		beforeEach(() => {
			const messagesOf = source.chats.messages;
			source.chats.list = () => [
				{ id: 7, name: 'Eva Recepční', unread: 0 },
				{ id: 20, name: 'vedení', kind: 'channel', private: true, unread: 0 },
			];
			source.chats.messages = (chatId) => ({ ...messagesOf(chatId), members: MEMBERS });
		});

		async function openChat(id) {
			await openChatPanel();
			click(`[data-quickbar-chat="${id}"]`);
			await settle();
		}

		it('kanál má v hlavičce počet členů a seznam je sbalený', async () => {
			await openChat(20);

			const toggle = $('[data-quickbar-members-toggle]');
			expect(toggle.textContent).toContain('Soukromý kanál · 3 členové');
			expect(toggle.getAttribute('aria-expanded')).toBe('false');
			expect(toggle.getAttribute('aria-controls')).toBe('quickbar-chat-members');
			expect($('[data-quickbar-chat-members]').hidden).toBe(true);
		});

		it('klik seznam rozbalí se jmény a (vy), další klik ho sbalí', async () => {
			await openChat(20);
			click('[data-quickbar-members-toggle]');

			const $members = $('[data-quickbar-chat-members]');
			expect($members.hidden).toBe(false);
			expect($$('.quickbar-chat-member-name').map((el) => el.textContent)).toEqual(['Eva Recepční', 'Jan Novák', 'Petra Malá']);
			expect($$('.quickbar-chat-member-me').map((el) => el.textContent)).toEqual(['(vy)']);
			expect($('[data-quickbar-members-toggle]').getAttribute('aria-expanded')).toBe('true');

			click('[data-quickbar-members-toggle]');
			expect($members.hidden).toBe(true);
			expect($('[data-quickbar-members-toggle]').getAttribute('aria-expanded')).toBe('false');
		});

		it('přímá konverzace počet členů nemá', async () => {
			await openChat(7);

			expect($('[data-quickbar-members-toggle]')).toBeNull();
			expect($('[data-quickbar-chat-current] .quickbar-chat-current-sub').textContent).toBe('Kolega');
		});

		it('vlákno odpovědí seznam schová', async () => {
			await openChat(20);
			click('[data-quickbar-members-toggle]');
			expect($('[data-quickbar-chat-members]').hidden).toBe(false);

			click('[data-quickbar-replies="1"]');
			await settle();
			expect($('[data-quickbar-chat-members]').hidden).toBe(true);
		});
	});

	describe('ztlumená konverzace', () => {
		let muted;

		beforeEach(() => {
			muted = true;
			const messagesOf = source.chats.messages;
			source.chats.list = () => [
				{ id: 7, name: 'Eva Recepční', unread: 2 },
				{ id: 20, name: 'recepce', kind: 'channel', unread: 3, muted: true },
			];
			source.chats.messages = (chatId) => ({ ...messagesOf(chatId), muted: chatId === 20 && muted });
			source.chats.menu = () => [{ id: 'mute', label: muted ? 'Zrušit ztlumení' : 'Ztlumit' }];
			source.chats.menuAction = () => {
				muted = !muted;
			};
		});

		it('v seznamu má zvonek a nepřečtené šedě', async () => {
			await openChatPanel();

			const $muted = $('[data-quickbar-chat="20"]');
			expect($muted.querySelector('.quickbar-chat-name .quickbar-chat-muted')).not.toBeNull();
			expect($muted.querySelector('.quickbar-chat-name .visually-hidden').textContent).toBe(' Ztlumeno');
			expect($muted.querySelector('.badge').classList.contains('bg-secondary')).toBe(true);

			const $other = $('[data-quickbar-chat="7"]');
			expect($other.querySelector('.quickbar-chat-muted')).toBeNull();
			expect($other.querySelector('.badge').classList.contains('bg-danger')).toBe(true);
		});

		it('hlavička ukáže zvonek a změnu z nabídky hned', async () => {
			await openChatPanel();
			click('[data-quickbar-chat="20"]');
			await settle();
			expect($('[data-quickbar-chat-current] .quickbar-chat-current-name .quickbar-chat-muted')).not.toBeNull();

			click('[data-quickbar-chat-plus]');
			await settle();
			click('[data-quickbar-chat-menu-item="mute"]');
			await settle();
			expect($('[data-quickbar-chat-current] .quickbar-chat-muted')).toBeNull();

			click('[data-quickbar-chat-plus]');
			await settle();
			click('[data-quickbar-chat-menu-item="mute"]');
			await settle();
			expect($('[data-quickbar-chat-current] .quickbar-chat-muted')).not.toBeNull();
		});

		it('přímá konverzace bez muted zvonek nemá', async () => {
			await openChatPanel();
			click('[data-quickbar-chat="7"]');
			await settle();

			expect($('[data-quickbar-chat-current] .quickbar-chat-muted')).toBeNull();
		});
	});
});

// Zdroj se sledovanými vlákny
function threadsSource() {
	const slack = slackSource().chats;
	let following = true;
	let newReplies = 3;
	return {
		chats: {
			...slack,
			list: () => [
				{ id: 7, name: 'Eva Recepční', unread: 2 },
				{ id: 20, name: 'recepce', kind: 'channel', private: true, unread: 0 },
			],
			threadsUnread: () => newReplies,
			threads: () => [
				{ chatId: 20, chatName: 'recepce', kind: 'channel', private: true, messageId: 1, text: 'Kdo má v pondělí ranní?', author: 'Eva Recepční',
					replyCount: 5, unread: newReplies, lastReplyTime: '9:30', lastReply: { author: 'Jan Novák', text: 'Já' } },
				{ chatId: 7, chatName: 'Eva Recepční', messageId: 2, text: 'Rozpis', replyCount: 1, unread: 0 },
			],
			messages: (chatId) => {
				calls.push(['messages', chatId]);
				return {
					messages: [
						{ id: 1, mine: false, author: 'Eva Recepční', text: 'Kdo má v pondělí ranní?', time: '9:00', replyCount: 5, newReplies },
						{ id: 4, mine: true, text: 'Rozpis', time: '9:05', replyCount: 2, newReplies: 1 },
						{ id: 5, mine: true, text: 'Díky', time: '9:06', replyCount: 6, newReplies: 5 },
					],
					threadsWithNews: newReplies ? [1, 4, 5] : [],
				};
			},
			thread: (chatId, messageId) => {
				calls.push(['thread', chatId, messageId]);
				newReplies = 0;
				return {
					parent: { id: messageId, mine: false, author: 'Eva Recepční', text: 'Kdo má v pondělí ranní?', time: '9:00' },
					replies: [{ id: 11, mine: false, author: 'Jan Novák', text: 'Já', time: '9:10' }],
					following,
				};
			},
			follow: (chatId, messageId, value) => {
				calls.push(['follow', chatId, messageId, value]);
				following = value;
			},
		},
	};
}

describe('sledovaná vlákna', () => {
	beforeEach(() => {
		source = threadsSource();
		setQuickbarSource(source);
	});

	it('bez threads() řádek Vlákna nebude', async () => {
		setQuickbarSource(slackSource());
		await openChatPanel();
		expect($('[data-quickbar-threads]')).toBeNull();
	});

	it('Vlákna jsou u Nového kanálu s počtem, který se přičte k počtu u Chatu; v seznamu jsou jen chaty', async () => {
		await openChatPanel();
		await settle();

		const button = $('[data-quickbar-chat-tools] [data-quickbar-threads]');
		expect(button.querySelector('.badge').textContent).toBe('3');
		expect($('[data-quickbar-chats] [data-quickbar-threads], [data-quickbar-chats] [data-quickbar-reminders]')).toBeNull();
		expect($('[data-quickbar-count="chat"]').textContent).toBe('5');
	});

	it('seznam vláken ukáže konverzaci, zprávu a poslední odpověď, klik otevře vlákno v konverzaci', async () => {
		await openChatPanel();
		click('[data-quickbar-threads]');
		await settle();

		expect($('[data-quickbar-chats]').hidden).toBe(true);
		const items = $$('[data-quickbar-thread-open]');
		expect(items).toHaveLength(2);
		expect(items[0].classList.contains('quickbar-chat-row--unread')).toBe(true);
		expect(items[0].querySelector('.quickbar-chat-avatar--channel')).not.toBeNull();
		expect(items[0].querySelector('.fa-lock')).not.toBeNull();
		expect(items[0].textContent).toContain('Kdo má v pondělí ranní?');
		expect(items[0].textContent).toContain('Jan Novák: Já');
		expect(items[0].textContent).toContain('5 odpovědí · poslední 9:30');
		expect(items[1].querySelector('.badge')).toBeNull();

		click(items[0]);
		await settle();

		expect(calls).toContainEqual(['thread', 20, 1]);
		expect($('[data-quickbar-chat-current]').textContent).toContain('Vlákno');
		expect($('[data-quickbar-thread]').textContent).toContain('Já');
		// Vlákno je přečtené - počet u Chatu jen z konverzací
		expect($('[data-quickbar-count="chat"]').textContent).toBe('2');

		click('[data-quickbar-replies-back]');
		await settle();
		expect($('[data-quickbar-chat-current]').textContent).toContain('recepce');
	});

	it('Esc ze seznamu vláken vrátí seznam konverzací', async () => {
		await openChatPanel();
		click('[data-quickbar-threads]');
		await settle();
		key('Escape', document.body);

		expect($('[data-quickbar-chats]').hidden).toBe(false);
	});

	it('prázdný seznam vláken má hlášku', async () => {
		source.chats.threads = () => [];
		await openChatPanel();
		click('[data-quickbar-threads]');
		await settle();

		expect($('[data-quickbar-threads-list]').textContent).toContain('Zatím nesledujete žádné vlákno s odpověďmi.');
	});

	it('nové odpovědi zvýrazní souhrn vlákna s tvarem podle počtu', async () => {
		await openThread();

		const summary = (id) => message(id).querySelector('.quickbar-message-replies');
		expect(summary(1).classList.contains('quickbar-message-replies--new')).toBe(true);
		expect(summary(1).querySelector('.quickbar-message-replies-dot')).not.toBeNull();
		expect(summary(1).textContent).toContain('3 nové odpovědi');
		expect(summary(4).textContent).toContain('1 nová odpověď');
		expect(summary(5).textContent).toContain('5 nových odpovědí');
	});

	it('pruh nových odpovědí otevře první vlákno a po zavření vlákna zmizí', async () => {
		await openThread();
		const $news = $('[data-quickbar-chat-news]');
		expect($news.hidden).toBe(false);
		expect($news.textContent).toContain('Nové odpovědi ve 3 vláknech');

		click('[data-quickbar-thread-news]');
		await settle();
		expect(calls).toContainEqual(['thread', 7, 1]);
		expect($news.hidden).toBe(true);

		click('[data-quickbar-replies-back]');
		await settle();
		expect($news.hidden).toBe(true);
		expect(message(1).querySelector('.quickbar-message-replies--new')).toBeNull();
	});

	it('Sledovat / Přestat sledovat v hlavičce vlákna', async () => {
		await openThread();
		click('[data-quickbar-replies="1"]');
		await settle();

		const button = () => $('[data-quickbar-thread-follow]');
		expect(button().textContent).toBe('Přestat sledovat');
		click(button());
		await settle();

		expect(calls).toContainEqual(['follow', 7, 1, false]);
		expect(button().textContent).toBe('Sledovat');
	});

	it('bez follow tlačítko Sledovat nebude', async () => {
		delete source.chats.follow;
		await openThread();
		click('[data-quickbar-replies="1"]');
		await settle();

		expect($('[data-quickbar-thread-follow]')).toBeNull();
	});
});

// Zdroj s připomínkami (Připomenout později)
function remindersSource() {
	const base = threadsSource().chats;
	let reminders = [
		{ id: 31, chatId: 20, chatName: 'recepce', kind: 'channel', private: true, messageId: 1, text: 'Kdo má v pondělí ranní?', author: 'Eva Recepční', at: 'dnes v 9:20', due: true },
		{ id: 32, chatId: 20, chatName: 'recepce', kind: 'channel', private: true, messageId: 11, parentId: 1, text: 'Já', author: 'Jan Novák', at: 'zítra v 8:00', due: false },
	];
	return {
		chats: {
			...base,
			remindersCount: () => reminders.length,
			reminders: () => {
				calls.push(['reminders']);
				return reminders;
			},
			reminderDone: (id) => {
				calls.push(['reminderDone', id]);
				reminders = reminders.filter((r) => r.id !== id);
			},
		},
	};
}

describe('připomenout později', () => {
	beforeEach(() => {
		source = remindersSource();
		setQuickbarSource(source);
	});

	it('bez reminders() řádek nebude', async () => {
		setQuickbarSource(threadsSource());
		await openChatPanel();
		await settle();
		expect($('[data-quickbar-reminders]')).toBeNull();
	});

	it('Připomenout později je u Nového kanálu s neutrálním počtem, k počtu u Chatu se nepřičte', async () => {
		await openChatPanel();
		await settle();

		const badge = $('[data-quickbar-chat-tools] [data-quickbar-reminders] .badge');
		expect(badge.textContent).toBe('2');
		expect(badge.classList.contains('bg-danger')).toBe(false);
		expect($('[data-quickbar-count="chat"]').textContent).toBe('5');
	});

	it('řádek je vidět i bez připomínek', async () => {
		source.chats.remindersCount = () => 0;
		await openChatPanel();
		await settle();

		const row = $('[data-quickbar-reminders]');
		expect(row).not.toBeNull();
		expect(row.querySelector('.badge')).toBeNull();
	});

	it('seznam ukáže konverzaci, zprávu a čas, prošlé zvýrazní', async () => {
		await openChatPanel();
		click('[data-quickbar-reminders]');
		await settle();

		expect($('[data-quickbar-chats]').hidden).toBe(true);
		const items = $$('.quickbar-reminder-item');
		expect(items).toHaveLength(2);
		expect(items[0].classList.contains('quickbar-reminder-item--due')).toBe(true);
		expect(items[1].classList.contains('quickbar-reminder-item--due')).toBe(false);
		expect(items[0].querySelector('.fa-lock')).not.toBeNull();
		expect(items[0].textContent).toContain('Kdo má v pondělí ranní?');
		expect(items[0].textContent).toContain('dnes v 9:20');
	});

	it('klik otevře konverzaci a zvýrazní zprávu', async () => {
		await openChatPanel();
		click('[data-quickbar-reminders]');
		await settle();
		click('[data-quickbar-reminder-open="31"]');
		await settle();

		expect(calls).toContainEqual(['messages', 20]);
		expect($('[data-quickbar-chat-current]').textContent).toContain('recepce');
		expect(message(1).classList.contains('quickbar-message--highlight')).toBe(true);
	});

	it('připomínka odpovědi otevře vlákno a zvýrazní odpověď', async () => {
		await openChatPanel();
		click('[data-quickbar-reminders]');
		await settle();
		click('[data-quickbar-reminder-open="32"]');
		await settle();

		expect(calls).toContainEqual(['thread', 20, 1]);
		expect($('[data-quickbar-chat-current]').textContent).toContain('Vlákno');
		expect(message(11).classList.contains('quickbar-message--highlight')).toBe(true);
	});

	it('Hotovo připomínku odebere a načte seznam i počet znovu', async () => {
		await openChatPanel();
		click('[data-quickbar-reminders]');
		await settle();
		click('[data-quickbar-reminder-done="31"]');
		await settle();

		expect(calls).toContainEqual(['reminderDone', 31]);
		expect($$('.quickbar-reminder-item')).toHaveLength(1);
		click('[data-quickbar-channels-back]');
		await settle();
		expect($('[data-quickbar-reminders] .badge').textContent).toBe('1');
	});

	it('prázdný seznam má hlášku a Esc vrátí seznam konverzací', async () => {
		source.chats.reminders = () => [];
		await openChatPanel();
		click('[data-quickbar-reminders]');
		await settle();

		expect($('[data-quickbar-reminders-list]').textContent).toContain('Nemáte žádné připomínky.');
		key('Escape', document.body);
		expect($('[data-quickbar-chats]').hidden).toBe(false);
	});

	it('po připomenutí zprávy se počet obnoví', async () => {
		let count = 0;
		source.chats.remindersCount = () => count;
		await openThread();
		count = 1;
		click('[data-quickbar-message-more="1"]');
		click('[data-quickbar-remind="1"]');
		click('[data-quickbar-remind-when="1h"]');
		await settle();
		click('[data-quickbar-chat-back]');
		await settle();

		expect($('[data-quickbar-reminders] .badge').textContent).toBe('1');
	});
});
