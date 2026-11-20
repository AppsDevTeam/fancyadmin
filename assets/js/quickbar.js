// Rychlá lišta dole uprostřed (@quickbar.latte) - hledání a panely Notifikace, Úkoly, Chat.
// Data dodává projekt přes setQuickbarSource(); bez něj jsou panely prázdné.
//
// Zdroj dat (všechny metody vrací Promise, chybějící metoda = prázdný výsledek):
//   search(query) → [{ label, items: [{ label, meta?, initials?, icon?, tone?, badge?: { label, class }, href?, panel? }] }]
//   notifications: { list() → [{ id, icon, tone?, title, text, time, unread, href? }], read(id), readAll() }
//   todos: { list() → [{ id, text, due?, done }], add(text) → todo, setDone(id, done) }
//   chats: { list() → [{ id, name, unread, preview?, time?, kind?: 'colleague'|'group', initials?, contact? }] (od nejnovější zprávy;
//            contact = kolega bez konverzace, v seznamu jen při hledání),
//            messages(chatId) → [{ mine, text, time, link?: { text, href } }], send(chatId, text) → message, read(chatId) }
//            link = část textu zprávy jako odkaz (např. jméno pacienta na jeho kartu)
//            Volitelně (bez nich lišta plus ani výběr zpráv neukáže):
//            messages(chatId) → { messages, topic?: { label, href? } } - téma konverzace v pruhu nad polem zprávy,
//              zpráva navíc s id a tag?: { text, href? } (řada zpráv se stejným štítkem je jeden blok se štítkem nahoře),
//            menu(chatId) → [{ id, icon, label, meta? }] - nabídka pod plusem vlevo od pole zprávy,
//            menuAction(chatId, id) - po dokončení lišta zprávy načte znovu (téma se mohlo změnit),
//            clearTopic(chatId) - tlačítko Ukončit v pruhu tématu,
//            selectionActions(chatId) → [{ id, icon?, label }] - v nabídce přibude „Vybrat zprávy“,
//            selectionAction(chatId, id, messageIds) → false = zrušeno (výběr zůstane), jinak se výběr ukončí.
//            Akce si můžou nechat vybrat položku přes pickInQuickbar().
//            Kanály, vlákna, připnuté zprávy, připomenutí a přílohy (volitelné, bez metody lišta prvek neukáže):
//            list() položka může mít kind: 'channel' (avatar „#“) a private: true (zámek),
//            browseChannels() → [{ id, name, private, members, joined }] - tlačítko Procházet kanály (id = id konverzace),
//            joinChannel(id) → chat, createChannel({ name, private, memberIds }) → chat - tlačítko Nový kanál,
//            colleagues() → [{ id, name, initials? }] - výběr členů nového kanálu (memberIds = jejich id),
//            messages(chatId) → { messages, topic?, pinned?: [{ id, text, author?, time? }] } - pruh Připnuté nad zprávami,
//            zpráva navíc s author?, system?, pinned?, canPin?, replyCount?, replyAuthors?: [iniciály], lastReplyTime?,
//              attachments?: [{ name, url, size? (bajty nebo text), image? }],
//            thread(chatId, messageId) → { parent, replies } - vlákno odpovědí na zprávu,
//            send(chatId, text, options) - options = { parentId?, files?: File[] } jen u odpovědi ve vlákně nebo s přílohami,
//            attachments: true - sponka u pole zprávy, soubory jdou v options.files (text pak může být prázdný),
//            pin(chatId, messageId, pinned) - Připnout / Odepnout u zprávy s canPin,
//            remind(chatId, messageId, when) → { label } - when = '20m' | '1h' | 'tomorrow' | čas v ISO 8601 (UTC),
//              label se u zprávy krátce ukáže jako potvrzení.
//            Sledovaná vlákna (volitelné):
//            threadsUnread() → počet nových odpovědí ve sledovaných vláknech - přičte se k počtu u Chatu,
//            threads() → [{ chatId, chatName, kind?, private?, messageId, text, author?, time?, replyCount, unread,
//              lastReplyTime?, lastReply?: { author, text } }] od nejnovější odpovědi - tlačítko Vlákna vedle Nového kanálu,
//            zpráva navíc s newReplies? (počet nových odpovědí - zvýrazněný souhrn vlákna),
//            messages(chatId) → { …, threadsWithNews?: [messageId, …] } - pruh „Nové odpovědi ve vláknech“ nad zprávami,
//            thread(chatId, messageId) → { parent, replies, following? }, follow(chatId, messageId, following) - Sledovat / Přestat sledovat.
//            Otevření vlákna (thread) ho označí přečtené, po zavření vlákna lišta načte zprávy znovu.
//            Připomenout později (volitelné, tlačítko vedle Vláken):
//            remindersCount() → počet čekajících připomínek - jen u řádku, k počtu u Chatu se nepřičítá (připomínky se ozvou samy),
//            reminders() → [{ id, chatId, chatName, kind?, private?, messageId, parentId?, text, author?, at, due }] podle času;
//              at = hotový popisek času („dnes v 14:20“), due = čas už nastal; klik otevře konverzaci (u odpovědi vlákno)
//              a zvýrazní zprávu, reminderDone(id) - tlačítko Hotovo u připomínky.
// Otevřený chat obnovuje projekt voláním refreshQuickbarChat() (polling, push).
//
// Na mobilu je v liště i Menu (@quickbarMenu.latte) místo bočního menu a AI se neroztahuje na celou plochu.
//
// AI asistent zdroj nepotřebuje - panel hostí widget adt-ai-chat (assets/js/aiChat.js), konfiguraci dá AiPresenter.
// Konverzace můžou nést volitelně `preview` (náhled poslední zprávy) a `unread` (nepřečtená odpověď).

import * as speech from './quickbarSpeech.js';
import naja from 'naja';
import { onContentLoaded } from './naja';

const SEARCH_DEBOUNCE_MS = 200;
const AI_UNREAD_KEY = 'fancyadmin-quickbar-ai-unread';
const OPEN_KEY = 'fancyadmin-quickbar-open';
// Posuvné části panelů - podle nich se ukazují linky pod hlavičkou a nad patičkou
const PANEL_SCROLLERS = '.quickbar-panel-body, .quickbar-thread, .quickbar-channels, .quickbar-ai-recent, .ai-chat__messages';

// Zlom sm Bootstrapu, stejně jako media-breakpoint-down(sm) v _quickbar.scss
const mobileQuery = window.matchMedia('(max-width: 575.98px)');

let source = {};

const state = {
	open: null,
	query: '',
	searchRequest: 0,
	// Poslední hledání, jehož výsledky jsou vidět, a hledání, na jehož výsledky čeká stisknutý Enter
	searchShown: 0,
	enterRequest: 0,
	// Výsledek vybraný šipkami (index v resultItems), -1 = žádný
	selected: -1,
	notifications: [],
	todos: [],
	chats: [],
	chatView: 'list',
	chatQuery: '',
	activeChat: null,
	messages: [],
	// Téma otevřené konverzace ({ label, href? }) - zdroj ho vrací spolu se zprávami
	chatTopic: null,
	// Nabídka pod plusem (null = zavřená) a akce nad vybranými zprávami
	chatMenu: null,
	chatSelecting: false,
	chatSelected: new Set(),
	chatSelectionActions: [],
	// Otevřený výběr položky s hledáním (pickInQuickbar)
	picker: null,
	// Nový kanál nebo procházení kanálů místo seznamu konverzací ({ mode: 'create' | 'browse', … })
	channels: null,
	// Otevřené vlákno odpovědí ({ messageId, parent, replies }), null = celá konverzace
	chatReplies: null,
	chatPinned: [],
	chatPinnedOpen: false,
	chatHighlight: null,
	// Zpráva s otevřenou nabídkou pod třemi tečkami ({ id, view: 'menu' | 'remind' | 'custom' }) a krátké potvrzení u zprávy ({ id, text })
	chatRemind: null,
	chatNotice: null,
	chatFiles: [],
	chatUploading: false,
	// Nové odpovědi ve sledovaných vláknech (threadsUnread) a vlákna s novinkami v otevřené konverzaci
	threadsUnread: 0,
	// Čekající připomínky (remindersCount) - počet u řádku Připomenout později
	remindersCount: 0,
	chatThreadNews: [],
	ai: { config: null, loading: null, historyUrl: null, share: null, view: 'chat', full: false, query: '', conversations: [], conversationId: null, element: null, waiting: new Set(), unread: loadAiUnread(), restore: null, startNew: false, dictatedAt: 0 },
};

let currentBar = null;

export function setQuickbarSource(newSource) {
	source = newSource || {};
	// Před prvním initem načte počty init sám
	if (currentBar) {
		loadCounts();
	}
}

function call(path, ...args) {
	const [group, method] = path.includes('.') ? path.split('.') : [null, path];
	const fn = group ? source[group]?.[method] : source[method];
	return fn ? Promise.resolve(fn.apply(group ? source[group] : source, args)) : Promise.resolve(null);
}

const bar = () => document.querySelector('[data-quickbar]');
const panel = (name) => document.querySelector(`[data-quickbar-panel="${name}"]`);
const searchInput = () => document.querySelector('[data-quickbar-search]');
const aiFull = () => state.ai.full && !mobileQuery.matches;

function esc(value) {
	return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

// Text zprávy s prvním výskytem link.text jako odkazem; bez shody (text se mezitím změnil) prostý text
function messageHtml(message) {
	const text = String(message.text ?? '');
	const link = message.link;
	const at = link?.text ? text.indexOf(link.text) : -1;
	if (at < 0) {
		return esc(text);
	}
	return esc(text.slice(0, at))
		+ `<a class="quickbar-bubble-link" href="${esc(link.href)}">${esc(link.text)}</a>`
		+ esc(text.slice(at + link.text.length));
}

// Hledání bez ohledu na velikost písmen a diakritiku
function normalize(value) {
	return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function initials(name) {
	return String(name ?? '').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
}

// === Otevírání a zavírání ===

// focus: false - obnovené okno (jiná stránka, F5) nemá brát kurzor stránce
function open(name, focus = true) {
	if (!panel(name)) {
		return;
	}
	state.open = name;
	state.ai.full = false;
	// Chat se otevírá seznamem konverzací
	state.chatView = 'list';
	resetChatThreadState();
	resetChannels();
	render();

	if (name === 'search') {
		searchInput()?.focus();
		search(state.query);
	} else {
		if (focus) {
			searchInput()?.blur();
			panel(name).querySelector('.quickbar-panel-form input')?.focus();
		}
		if (name === 'chat') {
			if (focus) {
				panel('chat').querySelector('[data-quickbar-chat-query]')?.focus();
			}
			loadChats();
		}
		if (name === 'ai') {
			openAi(focus);
		}
	}
}

function close() {
	if (state.open === null) {
		return;
	}
	state.open = null;
	state.query = '';
	state.ai.full = false;
	const input = searchInput();
	if (input) {
		input.value = '';
		input.blur();
	}
	render();
}

function toggle(name) {
	state.open === name ? close() : open(name);
}

function render() {
	const $bar = bar();
	if (!$bar) {
		return;
	}

	// Šířka zavřené lišty pro animaci roztažení na mobilu (_quickbar.scss)
	if (state.open === 'search' && !$bar.classList.contains('quickbar--open-search')) {
		$bar.style.setProperty('--quickbar-closed-width', `${$bar.offsetWidth}px`);
	}
	$bar.classList.toggle('quickbar--open-search', state.open === 'search');
	$bar.classList.toggle('quickbar--wide', state.open === 'search' || state.open === 'ai');
	document.querySelector('.quickbar-overlay').hidden = state.open === null;
	setColumnOpen(state.open !== null);
	document.querySelectorAll('[data-quickbar-panel]').forEach((el) => {
		el.hidden = el.dataset.quickbarPanel !== state.open;
	});
	panel('chat')?.classList.toggle('quickbar-panel--chat-thread', state.chatView === 'thread');
	panel('chat')?.classList.toggle('quickbar-panel--channels', state.chatView === 'list' && state.channels !== null);
	panel('ai')?.classList.toggle('quickbar-panel--full', aiFull());
	document.querySelectorAll('[data-quickbar-toggle]').forEach((button) => {
		const active = button.dataset.quickbarToggle === state.open;
		button.classList.toggle('btn-primary', active);
		button.classList.toggle('btn-glass', !active);
		button.setAttribute('aria-expanded', active ? 'true' : 'false');
	});

	const input = searchInput();
	if (input) {
		input.setAttribute('aria-expanded', state.open === 'search' ? 'true' : 'false');
		input.placeholder = state.open === 'search' ? input.dataset.placeholderOpen : input.dataset.placeholderClosed;
		if (state.open !== 'search') {
			input.removeAttribute('aria-activedescendant');
		}
	}

	readVisibleAi();
	renderCounts();
	renderNotifications();
	renderTodos();
	renderChat();
	renderAi();
	scheduleMeasure();
}

// Na širší obrazovce je panel sloupec vpravo a stránka se o něj zúží (_quickbar.scss,
// body.quickbar-column-open). Kalendář a grafy se přepočítají až po animaci šířky.
let resizeTimer = null;

function setColumnOpen(open) {
	if (document.body.classList.contains('quickbar-column-open') === open) {
		return;
	}
	document.body.classList.toggle('quickbar-column-open', open);
	clearTimeout(resizeTimer);
	resizeTimer = setTimeout(() => window.dispatchEvent(new Event('resize')), 300);
}

function renderCounts() {
	const counts = {
		notifications: state.notifications.filter((n) => n.unread).length,
		todo: state.todos.filter((t) => !t.done).length,
		chat: state.chats.reduce((sum, c) => sum + (c.unread || 0), 0) + state.threadsUnread,
		ai: state.ai.unread.size,
	};
	document.querySelectorAll('[data-quickbar-count]').forEach((badge) => {
		const count = counts[badge.dataset.quickbarCount] || 0;
		badge.textContent = count;
		badge.hidden = count === 0;
	});
}

function loadCounts() {
	if (!bar()) {
		return;
	}
	Promise.all([
		call('notifications.list').then((list) => { state.notifications = list || []; }),
		call('todos.list').then((list) => { state.todos = list || []; }),
		call('chats.list').then((list) => { state.chats = list || []; }),
		loadThreadsUnread(false),
		loadRemindersCount(false),
	]).then(render);
}

function loadRemindersCount(rerender = true) {
	if (!chatSourceHas('remindersCount')) {
		return Promise.resolve();
	}
	return call('chats.remindersCount').then((count) => {
		state.remindersCount = Number(count) || 0;
		if (rerender) {
			render();
		}
	});
}

function loadThreadsUnread(rerender = true) {
	if (!chatSourceHas('threadsUnread')) {
		return Promise.resolve();
	}
	return call('chats.threadsUnread').then((count) => {
		state.threadsUnread = Number(count) || 0;
		if (rerender) {
			render();
		}
	});
}

// === Hledání ===

let searchTimer = null;

function search(query) {
	const request = ++state.searchRequest;
	clearTimeout(searchTimer);
	searchTimer = setTimeout(() => {
		call('search', query.trim()).then((groups) => {
			if (request === state.searchRequest && state.open === 'search') {
				renderResults(groups || [], query.trim());
				state.searchShown = request;
				if (state.enterRequest === request) {
					openSelectedResult();
				}
			}
		});
	}, query ? SEARCH_DEBOUNCE_MS : 0);
}

let resultItems = [];

function renderResults(groups, query) {
	const $results = document.querySelector('[data-quickbar-results]');
	if (!$results) {
		return;
	}
	groups = groups.filter((group) => group.items && group.items.length);
	resultItems = groups.flatMap((group) => group.items);

	if (!groups.length) {
		const text = query ? $results.dataset.noResults.replace('%query%', query) : $results.dataset.empty;
		$results.innerHTML = `<div class="quickbar-empty">${esc(text)}</div>`;
		selectResult(-1);
		return;
	}

	let index = 0;
	$results.innerHTML = groups.map((group) => `
		<div class="quickbar-group-title" role="presentation">${esc(group.label)}</div>
		${group.items.map((item) => resultItem(item, index++)).join('')}
	`).join('');
	// Nové výsledky = vybraný první, Enter ho rovnou otevře
	selectResult(0);
}

function resultItem(item, index) {
	const tag = item.href ? 'a' : 'button';
	const attrs = item.href ? `href="${esc(item.href)}"` : 'type="button"';
	const leading = item.initials
		? `<span class="quickbar-avatar">${esc(item.initials)}</span>`
		: `<span class="quickbar-item-icon quickbar-item-icon--tone-${esc(item.tone || 'muted')}"><i class="${esc(item.icon)}"></i></span>`;

	return `<${tag} class="quickbar-item" ${attrs} id="quickbar-result-${index}" role="option" aria-selected="false" data-quickbar-result="${index}">
		${leading}
		<span class="quickbar-item-text">
			<span class="quickbar-item-label ${item.meta ? 'quickbar-item-label--strong' : ''}">${esc(item.label)}</span>
			${item.meta ? `<span class="quickbar-item-meta">${esc(item.meta)}</span>` : ''}
		</span>
		${item.badge ? `<span class="badge ${esc(item.badge.class)}">${esc(item.badge.label)}</span>` : ''}
		<kbd class="quickbar-key quickbar-item-enter" aria-hidden="true">↵</kbd>
	</${tag}>`;
}

// Výběr výsledku šipkami nebo myší: kurzor zůstává v poli hledání (dá se psát dál), vybraný
// řádek se jen označí a čtečce ho ohlásí aria-activedescendant pole
function selectResult(index, reveal = true) {
	state.selected = index;
	let $selected = null;
	document.querySelectorAll('[data-quickbar-result]').forEach((el) => {
		const selected = Number(el.dataset.quickbarResult) === index;
		el.classList.toggle('quickbar-item--selected', selected);
		el.setAttribute('aria-selected', selected ? 'true' : 'false');
		if (selected) {
			$selected = el;
		}
	});
	const input = searchInput();
	$selected ? input?.setAttribute('aria-activedescendant', $selected.id) : input?.removeAttribute('aria-activedescendant');

	if ($selected && reveal) {
		// U prvního výsledku skupiny ukázat i její nadpis
		const title = $selected.previousElementSibling;
		if (title?.classList.contains('quickbar-group-title')) {
			title.scrollIntoView({ block: 'nearest' });
		}
		$selected.scrollIntoView({ block: 'nearest' });
	}
}

// Šipky se na konci seznamu zastaví (držená šipka nepřeskočí zpět na začátek)
function moveSelection(step) {
	if (resultItems.length) {
		selectResult(Math.min(Math.max(state.selected + step, 0), resultItems.length - 1));
	}
}

// Otevře vybraný výsledek stejně jako klik - odkaz jde ajaxem (naja.js), panel přes obsluhu kliku níž
function openSelectedResult() {
	state.enterRequest = 0;
	document.querySelector(`[data-quickbar-result="${state.selected}"]`)?.click();
}

// === Notifikace ===

function renderNotifications() {
	const $list = document.querySelector('[data-quickbar-list="notifications"]');
	if (!$list) {
		return;
	}
	setHtml($list, state.notifications.length
		// S odkazem (nová zpráva k pacientovi, pacient přišel) je notifikace odkaz - klik ji přečte a naviguje
		? state.notifications.map((n) => `
			<${n.href ? `a href="${esc(n.href)}"` : 'button type="button"'} class="quickbar-item quickbar-notification" data-quickbar-notification="${esc(n.id)}">
				<span class="quickbar-item-icon quickbar-item-icon--tone-${esc(n.tone || 'muted')}"><i class="${esc(n.icon)}"></i></span>
				<span class="quickbar-item-text">
					<span class="quickbar-item-label quickbar-item-label--strong">${esc(n.title)}</span>
					<span class="quickbar-notification-text">${esc(n.text)}</span>
					<span class="quickbar-notification-time">${esc(n.time)}</span>
				</span>
				${n.unread ? '<span class="quickbar-unread-dot"></span>' : ''}
			</${n.href ? 'a' : 'button'}>`).join('')
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`);
}

function readNotification(id) {
	const notification = state.notifications.find((n) => String(n.id) === id);
	if (!notification || !notification.unread) {
		return;
	}
	notification.unread = false;
	render();
	call('notifications.read', notification.id);
}

function readAllNotifications() {
	state.notifications.forEach((n) => { n.unread = false; });
	render();
	call('notifications.readAll');
}

// === Úkoly ===

function renderTodos() {
	const $list = document.querySelector('[data-quickbar-list="todo"]');
	if (!$list) {
		return;
	}
	setHtml($list, state.todos.length
		? state.todos.map((t) => `
			<label class="quickbar-item quickbar-todo ${t.done ? 'quickbar-todo--done' : ''}">
				<input type="checkbox" class="form-check-input" data-quickbar-todo="${esc(t.id)}" ${t.done ? 'checked' : ''}>
				<span class="quickbar-item-text">
					<span class="quickbar-item-label">${esc(t.text)}</span>
					${t.due ? `<span class="quickbar-item-meta"><i class="fa-regular fa-clock"></i> ${esc(t.due)}</span>` : ''}
				</span>
			</label>`).join('')
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`);
}

function setTodoDone(id, done) {
	const todo = state.todos.find((t) => String(t.id) === id);
	if (!todo) {
		return;
	}
	todo.done = done;
	render();
	call('todos.setDone', todo.id, done);
}

function addTodo(text) {
	call('todos.add', text).then((todo) => {
		state.todos.unshift(todo || { id: `local-${Date.now()}`, text, done: false });
		render();
	});
}

// === Chat ===

let renderedThread = { chat: null, count: 0 };

// Volitelné části chatu (téma, nabídka plusu, výběr zpráv) jen podle toho, co zdroj umí
const chatSourceHas = (method) => typeof source.chats?.[method] === 'function';

function renderChat() {
	const $list = document.querySelector('[data-quickbar-chats]');
	const $thread = document.querySelector('[data-quickbar-thread]');
	if (!$list || !$thread) {
		return;
	}
	$list.hidden = state.channels !== null;
	renderChatList($list);
	renderChatTools();
	renderChannels();
	renderChatCurrent();
	renderChatPinned();
	renderChatNews();
	renderChatTopic();
	renderChatSelection();
	renderChatFiles();
	renderChatMenu();
	renderPicker();

	const replies = state.chatReplies;
	$thread.classList.toggle('quickbar-thread--selecting', state.chatSelecting);
	setHtml($thread, replies ? repliesHtml(replies) : messageRuns(state.messages).map((run) => {
		const messages = run.messages.map((m) => messageRowHtml(m)).join('');
		return run.tag
			? `<div class="quickbar-message-group">
				<div class="quickbar-message-group-head">${messageTag(run.tag)}</div>
				${messages}
			</div>`
			: messages;
	}).join(''));
	// Na konec jen při nové zprávě nebo jiné konverzaci - polling by jinak vracel čtenáře dolů
	const shown = replies ? `${state.activeChat}:${replies.messageId}` : state.activeChat;
	const count = replies ? replies.replies.length : state.messages.length;
	if (renderedThread.chat !== shown || renderedThread.count !== count) {
		$thread.scrollTop = $thread.scrollHeight;
		renderedThread = { chat: shown, count };
	}
	placeMessageMenu();
}

const threadText = (key) => document.querySelector('[data-quickbar-thread]')?.dataset[key] ?? '';

function repliesLabel(count) {
	const form = count === 1 ? 'repliesOne' : (count >= 2 && count <= 4 ? 'repliesFew' : 'repliesMany');
	return threadText(form).replace('%count%', count);
}

function newRepliesLabel(count) {
	const form = count === 1 ? 'newRepliesOne' : (count >= 2 && count <= 4 ? 'newRepliesFew' : 'newRepliesMany');
	return threadText(form).replace('%count%', count);
}

function repliesHtml(replies) {
	return (replies.parent ? messageRowHtml(replies.parent, true) : '')
		+ `<div class="quickbar-replies-divider"><span>${esc(repliesLabel(replies.replies.length))}</span></div>`
		+ replies.replies.map((m) => messageRowHtml(m, true)).join('');
}

// Souvislá řada zpráv se stejným štítkem je jeden blok - je vidět, kde téma začíná a kde končí
function messageRuns(messages) {
	const runs = [];
	messages.forEach((m) => {
		const key = m.tag ? (m.tag.href ?? m.tag.text) : null;
		const last = runs[runs.length - 1];
		if (last && last.key === key) {
			last.messages.push(m);
		} else {
			runs.push({ key, tag: m.tag ?? null, messages: [m] });
		}
	});
	return runs;
}

// inReplies - zpráva ve vlákně odpovědí (bez souhrnu odpovědí a bez Odpovědět ve vlákně)
function messageRowHtml(m, inReplies = false) {
	const selectable = state.chatSelecting && m.id != null;
	const selected = selectable && state.chatSelected.has(String(m.id));
	const highlighted = m.id != null && state.chatHighlight === String(m.id);
	const classes = ['quickbar-message', m.mine ? 'quickbar-message--mine' : '', m.system ? 'quickbar-message--system' : '',
		selected ? 'quickbar-message--selected' : '', highlighted ? 'quickbar-message--highlight' : ''];
	const attributes = (m.id != null ? ` data-quickbar-message-id="${esc(m.id)}"` : '')
		+ (selectable ? ` role="checkbox" tabindex="0" aria-checked="${selected}" data-quickbar-message="${esc(m.id)}"` : '');
	const hasText = String(m.text ?? '') !== '';
	return `
		<div class="${classes.filter(Boolean).join(' ')}"${attributes}>
			${selectable ? '<span class="quickbar-message-check" aria-hidden="true"><i class="fa-solid fa-check"></i></span>' : ''}
			<div class="quickbar-message-content">
				${m.author && !m.mine && !m.system ? `<div class="quickbar-message-author">${esc(m.author)}</div>` : ''}
				${hasText || !m.attachments?.length ? `<div class="quickbar-bubble">${messageHtml(m)}</div>` : ''}
				${attachmentsHtml(m.attachments)}
				<div class="quickbar-message-time">${m.pinned ? '<i class="fa-solid fa-thumbtack quickbar-message-pin" aria-hidden="true"></i>' : ''}${esc(m.time)}</div>
				${inReplies ? '' : replySummaryHtml(m)}
				${state.chatNotice && m.id != null && state.chatNotice.id === String(m.id)
					? `<div class="quickbar-message-notice" role="status"><i class="fa-solid fa-clock"></i>${esc(state.chatNotice.text)}</div>`
					: ''}
				${messageActionsHtml(m, inReplies)}
			</div>
		</div>`;
}

function attachmentsHtml(attachments) {
	if (!attachments?.length) {
		return '';
	}
	return `<div class="quickbar-message-files">${attachments.map((file) => (file.image
		? `<a class="quickbar-file-image" href="${esc(file.url)}" target="_blank" rel="noopener" title="${esc(file.name)}"><img src="${esc(file.url)}" alt="${esc(file.name)}" loading="lazy"></a>`
		: `<a class="quickbar-file" href="${esc(file.url)}" target="_blank" rel="noopener">
			<i class="fa-regular fa-file"></i>
			<span class="quickbar-file-name">${esc(file.name)}</span>
			${file.size != null ? `<span class="quickbar-file-size">${esc(fileSize(file.size))}</span>` : ''}
		</a>`)).join('')}</div>`;
}

function fileSize(size) {
	if (typeof size !== 'number') {
		return String(size ?? '');
	}
	if (size < 1024) {
		return `${size} B`;
	}
	if (size < 1024 * 1024) {
		return `${Math.round(size / 1024)} kB`;
	}
	return `${(size / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

function replySummaryHtml(m) {
	const count = Number(m.replyCount) || 0;
	if (!count || m.id == null || !chatSourceHas('thread')) {
		return '';
	}
	const avatars = (m.replyAuthors ?? []).slice(0, 3).map((author) => `<span class="quickbar-message-replies-avatar">${esc(author)}</span>`).join('');
	const fresh = Number(m.newReplies) || 0;
	const inner = (fresh ? '<span class="quickbar-message-replies-dot" aria-hidden="true"></span>' : '')
		+ (avatars ? `<span class="quickbar-message-replies-avatars">${avatars}</span>` : '')
		+ `<span class="quickbar-message-replies-count">${esc(fresh ? newRepliesLabel(fresh) : repliesLabel(count))}</span>`
		+ (m.lastReplyTime ? `<span class="quickbar-message-replies-time">${esc(threadText('lastReply').replace('%time%', () => m.lastReplyTime))}</span>` : '');
	const classes = `quickbar-message-replies${fresh ? ' quickbar-message-replies--new' : ''}`;
	return state.chatSelecting
		? `<span class="${classes}">${inner}</span>`
		: `<button type="button" class="${classes}" data-quickbar-replies="${esc(m.id)}">${inner}</button>`;
}

// Akce zprávy jsou pod třemi tečkami vedle bubliny (jako na Facebooku), tlačítko se ukáže po najetí
// (fokusu) - jen u zprávy s id, ne u systémové a ne při výběru zpráv
function messageActionsHtml(m, inReplies) {
	if (m.id == null || m.system || state.chatSelecting) {
		return '';
	}
	const items = [
		!inReplies && chatSourceHas('thread') ? ['data-quickbar-reply', 'fa-solid fa-reply', threadText('reply')] : null,
		chatSourceHas('pin') && m.canPin ? ['data-quickbar-pin', 'fa-solid fa-thumbtack', threadText(m.pinned ? 'unpin' : 'pin')] : null,
		chatSourceHas('remind') ? ['data-quickbar-remind', 'fa-solid fa-clock', threadText('remind')] : null,
	].filter(Boolean);
	if (!items.length) {
		return '';
	}
	const id = esc(m.id);
	const open = state.chatRemind?.id === String(m.id);
	return `<div class="quickbar-message-actions${open ? ' quickbar-message-actions--open' : ''}">
		<button type="button" class="quickbar-message-action${open ? ' active' : ''}" title="${esc(threadText('actions'))}" aria-label="${esc(threadText('actions'))}"
			aria-haspopup="menu" aria-expanded="${open}" data-quickbar-message-more="${id}"><i class="fa-solid fa-ellipsis"></i></button>
		${open ? messageMenuHtml(items, id) : ''}
	</div>`;
}

const REMIND_OPTIONS = [['20m', 'remindMinutes'], ['1h', 'remindHour'], ['tomorrow', 'remindTomorrow'], ['custom', 'remindCustom']];

const menuItemHtml = (attribute, value, icon, label) => `
	<button type="button" class="quickbar-chat-menu-item" role="menuitem" ${attribute}="${value}">
		${icon ? `<span class="quickbar-chat-menu-icon"><i class="${icon}"></i></span>` : ''}
		<span class="quickbar-chat-menu-label">${esc(label)}</span>
	</button>`;

function messageMenuHtml(items, id) {
	const view = state.chatRemind.view;
	if (view === 'custom') {
		return `<div class="quickbar-remind-menu quickbar-remind-menu--custom">
			<input type="datetime-local" class="form-control form-control-sm date-default" aria-label="${esc(threadText('remindCustom'))}" data-quickbar-remind-at>
			<button type="button" class="btn btn-sm btn-primary" data-quickbar-remind-set>${esc(threadText('remindSet'))}</button>
		</div>`;
	}
	if (view === 'remind') {
		return `<div class="quickbar-remind-menu" role="menu" aria-label="${esc(threadText('remind'))}">
			${REMIND_OPTIONS.map(([when, key]) => menuItemHtml('data-quickbar-remind-when', when, null, threadText(key))).join('')}
		</div>`;
	}
	return `<div class="quickbar-remind-menu" role="menu" aria-label="${esc(threadText('actions'))}">
		${items.map(([attribute, icon, label]) => menuItemHtml(attribute, id, icon, label)).join('')}
	</div>`;
}

// Při výběru zpráv je štítek jen popisek - klik vybírá zprávu, nenaviguje
function messageTag(tag) {
	const inner = `<i class="fa-regular fa-tag"></i><span>${esc(tag.text)}</span>`;
	return tag.href && !state.chatSelecting
		? `<a class="quickbar-message-tag" href="${esc(tag.href)}">${inner}</a>`
		: `<span class="quickbar-message-tag">${inner}</span>`;
}

function renderChatTopic() {
	const $topic = document.querySelector('[data-quickbar-chat-topic]');
	if (!$topic) {
		return;
	}
	const topic = state.chatView === 'thread' && !state.chatReplies ? state.chatTopic : null;
	$topic.hidden = !topic || state.chatSelecting;
	if (!topic) {
		return;
	}
	const name = topic.href
		? `<a class="quickbar-chat-topic-name" href="${esc(topic.href)}">${esc(topic.label)}</a>`
		: `<span class="quickbar-chat-topic-name">${esc(topic.label)}</span>`;
	setHtml($topic, `
		<span class="quickbar-chat-topic-icon" aria-hidden="true"><i class="fa-regular fa-tag"></i></span>
		<span class="quickbar-chat-topic-text">
			<span class="quickbar-chat-topic-label">${esc($topic.dataset.label)}</span>
			${name}
		</span>
		${chatSourceHas('clearTopic') ? `<button type="button" class="btn btn-sm btn-ghost quickbar-chat-topic-clear" data-quickbar-chat-topic-clear>${esc($topic.dataset.clear)}</button>` : ''}`);
}

function renderChatSelection() {
	const $selection = document.querySelector('[data-quickbar-chat-selection]');
	const $form = panel('chat')?.querySelector('[data-quickbar-form="chat"]');
	if (!$selection || !$form) {
		return;
	}
	$selection.hidden = !state.chatSelecting;
	$form.hidden = state.chatSelecting;
	const $plus = $form.querySelector('[data-quickbar-chat-plus]');
	$plus.hidden = state.chatReplies !== null || (!chatSourceHas('menu') && !chatSourceHas('selectionActions'));
	$plus.setAttribute('aria-expanded', state.chatMenu ? 'true' : 'false');
	$plus.classList.toggle('active', state.chatMenu !== null);
	if (!state.chatSelecting) {
		return;
	}
	const count = state.chatSelected.size;
	const label = count ? $selection.dataset.count.replace('%count%', count) : $selection.dataset.hint;
	setHtml($selection, `
		<span class="quickbar-chat-selection-count${count ? '' : ' quickbar-chat-selection-count--hint'}">${esc(label)}</span>
		<span class="quickbar-chat-selection-actions">
			${state.chatSelectionActions.map((action, index) => `
				<button type="button" class="btn btn-sm ${index === 0 ? 'btn-primary' : 'btn-outline-secondary'}" data-quickbar-selection-action="${esc(action.id)}"${count ? '' : ' disabled'}>
					${action.icon ? `<i class="${esc(action.icon)}"></i>` : ''}${esc(action.label)}
				</button>`).join('')}
			<button type="button" class="btn btn-sm btn-ghost" data-quickbar-selection-cancel>${esc($selection.dataset.cancel)}</button>
		</span>`);
}

function renderChatMenu() {
	const $menu = document.querySelector('[data-quickbar-chat-menu]');
	if (!$menu) {
		return;
	}
	$menu.hidden = state.chatMenu === null;
	if (state.chatMenu === null) {
		return;
	}
	setHtml($menu, state.chatMenu.map((item) => `
		<button type="button" class="quickbar-chat-menu-item" role="menuitem" data-quickbar-chat-menu-item="${esc(item.id)}">
			<span class="quickbar-chat-menu-icon"><i class="${esc(item.icon || 'fa-regular fa-circle')}"></i></span>
			<span class="quickbar-chat-menu-text">
				<span class="quickbar-chat-menu-label">${esc(item.label)}</span>
				${item.meta ? `<span class="quickbar-chat-menu-meta">${esc(item.meta)}</span>` : ''}
			</span>
		</button>`).join(''));
}

const SELECT_MENU_ITEM = '__select';

function toggleChatMenu() {
	if (state.chatMenu !== null) {
		closeChatMenu();
		return;
	}
	const chatId = state.activeChat;
	const $menu = document.querySelector('[data-quickbar-chat-menu]');
	Promise.all([call('chats.menu', chatId), call('chats.selectionActions', chatId)]).then(([items, selectionActions]) => {
		if (state.activeChat !== chatId || state.chatView !== 'thread') {
			return;
		}
		state.chatSelectionActions = selectionActions || [];
		const menu = [
			...(items || []),
			...(state.chatSelectionActions.length ? [{ id: SELECT_MENU_ITEM, icon: 'fa-regular fa-list-check', label: $menu.dataset.select }] : []),
		];
		// Zdroj v téhle konverzaci nic nenabízí (např. bez oprávnění) - prázdná nabídka se neotevře
		if (!menu.length) {
			return;
		}
		state.chatMenu = menu;
		render();
		$menu.querySelector('[data-quickbar-chat-menu-item]')?.focus();
	});
}

function closeChatMenu(focusInput = false) {
	if (state.chatMenu === null) {
		return;
	}
	state.chatMenu = null;
	render();
	if (focusInput) {
		focusChatInput();
	}
}

function focusChatInput() {
	panel('chat')?.querySelector('[data-quickbar-form="chat"] input')?.focus();
}

function runChatMenuItem(id) {
	const chatId = state.activeChat;
	closeChatMenu();
	if (id === SELECT_MENU_ITEM) {
		startSelecting();
		return;
	}
	call('chats.menuAction', chatId, id).then(() => {
		if (state.activeChat === chatId) {
			loadMessages(chatId);
			focusChatInput();
		}
	});
}

function startSelecting() {
	state.chatSelecting = true;
	state.chatSelected = new Set();
	render();
}

function stopSelecting() {
	state.chatSelecting = false;
	state.chatSelected = new Set();
	render();
	focusChatInput();
}

function toggleMessageSelected(id) {
	state.chatSelected.has(id) ? state.chatSelected.delete(id) : state.chatSelected.add(id);
	render();
}

// Akce vrátí false, když si ji uživatel rozmyslel (např. zavřel výběr) - výběr pak zůstane
function runSelectionAction(id) {
	const chatId = state.activeChat;
	const ids = [...state.chatSelected];
	call('chats.selectionAction', chatId, id, ids).then((result) => {
		if (result === false || state.activeChat !== chatId) {
			return;
		}
		stopSelecting();
		loadMessages(chatId);
	});
}

function clearChatTopic() {
	const chatId = state.activeChat;
	call('chats.clearTopic', chatId).then(() => {
		if (state.activeChat === chatId) {
			state.chatTopic = null;
			render();
			loadMessages(chatId);
			focusChatInput();
		}
	});
}

function resetChatThreadState() {
	state.chatTopic = null;
	state.chatMenu = null;
	state.chatSelecting = false;
	state.chatSelected = new Set();
	state.chatReplies = null;
	state.chatPinned = [];
	state.chatPinnedOpen = false;
	state.chatThreadNews = [];
	state.chatHighlight = null;
	state.chatRemind = null;
	state.chatNotice = null;
	state.chatFiles = [];
	if (state.picker) {
		closePicker(null);
	}
}

// === Výběr položky s hledáním (překryje vlákno chatu) ===

/**
 * Výběr položky (pacienta, zakázky…) pro akci chatu. Ukáže se přes vlákno, dokud uživatel
 * nevybere nebo nezavře (Esc, šipka zpět). Vrací Promise s vybranou položkou, nebo null.
 *
 * options: { title, placeholder?, hint? (bez návrhů pod polem), minLength? (2),
 *            suggestions?: items | Promise<items>, search(query) → Promise<items> },
 *            items: [{ id, label, meta?, initials? }]
 */
export function pickInQuickbar(options) {
	return new Promise((resolve) => {
		if (state.picker) {
			closePicker(null);
		}
		state.picker = {
			title: options.title ?? '',
			placeholder: options.placeholder ?? '',
			minLength: options.minLength ?? 2,
			search: options.search,
			suggestions: [],
			suggestionsLoaded: false,
			hint: options.hint ?? '',
			query: '',
			results: null,
			active: 0,
			request: 0,
			resolve,
		};
		mountPicker();
		Promise.resolve(options.suggestions).then((list) => {
			if (state.picker?.resolve === resolve) {
				state.picker.suggestions = list || [];
				state.picker.suggestionsLoaded = true;
				renderPicker();
			}
		});
	});
}

// Kostra se vykreslí jednou při otevření - překreslení by bralo poli kurzor
function mountPicker() {
	const $picker = document.querySelector('[data-quickbar-picker]');
	if (!$picker || !state.picker) {
		return;
	}
	$picker.innerHTML = `
		<div class="quickbar-picker-header">
			<span class="quickbar-picker-title">${esc(state.picker.title)}</span>
			<button type="button" class="btn btn-icon btn-sm btn-ghost" title="${esc($picker.dataset.close)}" aria-label="${esc($picker.dataset.close)}" data-quickbar-picker-close><i class="fa-solid fa-xmark"></i></button>
		</div>
		<div class="quickbar-picker-search">
			<div class="quickbar-search-field">
				<i class="fa-regular fa-magnifying-glass"></i>
				<input type="text" class="form-control form-control-sm" autocomplete="off" placeholder="${esc(state.picker.placeholder)}" aria-label="${esc(state.picker.placeholder || state.picker.title)}" data-quickbar-picker-query>
			</div>
		</div>
		<div class="quickbar-panel-body quickbar-picker-list" role="listbox" data-quickbar-picker-list></div>`;
	render();
	$picker.querySelector('[data-quickbar-picker-query]').focus();
}

// Text filtruje návrhy; od minLength znaků platí výsledky hledání zdroje, do jejich příchodu návrhy
function pickerItems() {
	const picker = state.picker;
	const query = normalize(picker.query.trim());
	const suggestions = query ? picker.suggestions.filter((item) => normalize(`${item.label} ${item.meta ?? ''}`).includes(query)) : picker.suggestions;
	return query.length >= picker.minLength ? (picker.results ?? suggestions) : suggestions;
}

function pickerSearching() {
	const picker = state.picker;
	return picker.query.trim().length >= picker.minLength && picker.results !== null;
}

function renderPicker() {
	const $picker = document.querySelector('[data-quickbar-picker]');
	if (!$picker) {
		return;
	}
	$picker.hidden = state.picker === null;
	panel('chat')?.classList.toggle('quickbar-panel--picking', state.picker !== null);
	const $list = $picker.querySelector('[data-quickbar-picker-list]');
	if (!state.picker || !$list) {
		return;
	}
	const picker = state.picker;
	const searching = pickerSearching();
	const pending = picker.query.trim().length >= picker.minLength && picker.results === null;
	const items = pickerItems();
	let html = '';
	if (!items.length) {
		let text = '';
		if (searching) {
			text = $picker.dataset.empty;
		} else if (picker.query.trim() && !pending) {
			text = $picker.dataset.short.replace('%count%', picker.minLength);
		} else if (!picker.query.trim() && picker.suggestionsLoaded) {
			// Bez návrhů radí, co psát (hint), jinak kolik znaků stačí
			text = picker.hint || $picker.dataset.short.replace('%count%', picker.minLength);
		}
		html = text ? `<div class="quickbar-empty quickbar-picker-hint"><i class="fa-regular fa-magnifying-glass"></i>${esc(text)}</div>` : '';
	} else {
		html = (searching ? '' : `<div class="quickbar-group-title">${esc($picker.dataset.suggestions)}</div>`)
			+ items.map((item, index) => `
			<button type="button" class="quickbar-chat-row quickbar-picker-row${index === picker.active ? ' quickbar-picker-row--active' : ''}" role="option" aria-selected="${index === picker.active}" data-quickbar-picker-item="${index}">
				${chatAvatar({ name: item.label, initials: item.initials }, true)}
				<span class="quickbar-chat-text">
					<span class="quickbar-chat-name">${esc(item.label)}</span>
					${item.meta ? `<span class="quickbar-chat-preview">${esc(item.meta)}</span>` : ''}
				</span>
			</button>`).join('');
	}
	setHtml($list, html);
}

let pickerTimer = null;

function searchPicker(query) {
	const picker = state.picker;
	picker.query = query;
	picker.active = 0;
	picker.enterPending = false;
	clearTimeout(pickerTimer);
	if (query.trim().length < picker.minLength) {
		picker.results = null;
		renderPicker();
		return;
	}
	const request = ++picker.request;
	picker.results = null;
	renderPicker();
	pickerTimer = setTimeout(() => {
		Promise.resolve(picker.search?.(query.trim())).then((items) => {
			if (state.picker === picker && picker.request === request) {
				picker.results = items || [];
				renderPicker();
				// Enter stisknutý během hledání vybere první výsledek
				if (picker.enterPending) {
					picker.enterPending = false;
					const first = picker.results[0];
					if (first) {
						closePicker(first);
					}
				}
			}
		});
	}, SEARCH_DEBOUNCE_MS);
}

function movePicker(step) {
	const count = pickerItems().length;
	if (!count) {
		return;
	}
	state.picker.active = Math.min(count - 1, Math.max(0, state.picker.active + step));
	renderPicker();
	document.querySelector('.quickbar-picker-row--active')?.scrollIntoView({ block: 'nearest' });
}

function closePicker(item) {
	const picker = state.picker;
	if (!picker) {
		return;
	}
	state.picker = null;
	clearTimeout(pickerTimer);
	const $picker = document.querySelector('[data-quickbar-picker]');
	if ($picker) {
		$picker.innerHTML = '';
	}
	render();
	picker.resolve(item ?? null);
	if (state.open === 'chat' && state.chatView === 'thread' && !state.chatSelecting) {
		focusChatInput();
	}
}

function renderChatList($list) {
	const query = normalize(state.chatQuery.trim());
	const chats = query
		? state.chats.filter((c) => normalize(`${c.name} ${c.preview ?? ''}`).includes(query))
		: state.chats.filter((c) => !c.contact);
	// Nepřečtené nahoře, jinak pořadí zdroje (od nejnovější zprávy)
	const sorted = [...chats.filter((c) => c.unread), ...chats.filter((c) => !c.unread)];

	if (!sorted.length) {
		const text = query ? $list.dataset.noResults.replace('%query%', state.chatQuery.trim()) : $list.dataset.empty;
		setHtml($list, `<div class="quickbar-empty">${esc(text)}</div>`);
		return;
	}
	setHtml($list, sorted.map((c) => `
		<button type="button" class="quickbar-chat-row ${c.unread ? 'quickbar-chat-row--unread' : ''}" data-quickbar-chat="${esc(c.id)}">
			${chatAvatar(c)}
			<span class="quickbar-chat-text">
				<span class="quickbar-chat-line">
					<span class="quickbar-chat-name">${esc(c.name)}${c.private ? lockIcon : ''}</span>
					${c.time ? `<span class="quickbar-chat-time">${esc(c.time)}</span>` : ''}
				</span>
				<span class="quickbar-chat-line">
					<span class="quickbar-chat-preview">${esc(c.preview ?? '')}</span>
					${c.unread ? `<span class="badge bg-danger">${esc(c.unread)}</span>` : ''}
				</span>
			</span>
		</button>`).join(''));
}

// Polling překresluje seznam i vlákno - beze změny DOM nesahat, jinak blikne najetí a ztratí se fokus
const renderedHtml = new WeakMap();

function setHtml($el, html) {
	if (renderedHtml.get($el) !== html) {
		renderedHtml.set($el, html);
		$el.innerHTML = html;
	}
}

const lockIcon = '<i class="fa-solid fa-lock quickbar-chat-lock" aria-hidden="true"></i>';

function chatAvatar(chat, small = false) {
	const kind = { group: 'quickbar-chat-avatar--group', channel: 'quickbar-chat-avatar--channel' }[chat.kind] ?? '';
	const classes = ['quickbar-chat-avatar', kind, small ? 'quickbar-chat-avatar--sm' : ''];
	return `<span class="${classes.filter(Boolean).join(' ')}">${chat.kind === 'channel' ? '#' : esc(chat.initials || initials(chat.name))}</span>`;
}

function renderChatCurrent() {
	const $current = document.querySelector('[data-quickbar-chat-current]');
	const chat = state.chats.find((c) => c.id === state.activeChat);
	if (!$current || !chat) {
		return;
	}
	if (state.chatReplies) {
		const following = state.chatReplies.following;
		const follow = chatSourceHas('follow') && typeof following === 'boolean'
			? `<button type="button" class="btn btn-sm btn-ghost quickbar-thread-follow${following ? ' active' : ''}" aria-pressed="${following}" data-quickbar-thread-follow>${esc(following ? $current.dataset.unfollow : $current.dataset.follow)}</button>`
			: '';
		setHtml($current, `
			<button type="button" class="btn btn-icon btn-sm btn-ghost" title="${esc($current.dataset.threadBack)}" data-quickbar-replies-back><i class="fa-solid fa-arrow-left"></i></button>
			<span class="quickbar-chat-current-text">
				<span class="quickbar-chat-current-name">${esc($current.dataset.thread)}</span>
				<span class="quickbar-chat-current-sub">${esc(chat.name)}</span>
			</span>
			${follow}`);
		return;
	}
	const sub = {
		group: $current.dataset.group,
		channel: chat.private ? $current.dataset.privateChannel : $current.dataset.channel,
	}[chat.kind] ?? $current.dataset.colleague;
	setHtml($current, `
		<button type="button" class="btn btn-icon btn-sm btn-ghost" title="${esc($current.dataset.back)}" data-quickbar-chat-back><i class="fa-solid fa-arrow-left"></i></button>
		${chatAvatar(chat, true)}
		<span class="quickbar-chat-current-text">
			<span class="quickbar-chat-current-name">${esc(chat.name)}${chat.private ? lockIcon : ''}</span>
			<span class="quickbar-chat-current-sub">${esc(sub)}</span>
		</span>`);
}

function loadChats() {
	loadThreadsUnread();
	loadRemindersCount();
	return call('chats.list').then((list) => {
		state.chats = list || [];
		// Server označí konverzaci přečtenou až načtením zpráv, otevřená nesmí znovu ukázat počet
		const open = state.chatView === 'thread' && state.chats.find((c) => c.id === state.activeChat);
		if (open) {
			open.unread = 0;
		}
		render();
	});
}

// Zdroj vrací zprávy, nebo { messages, topic } u konverzace s tématem
function loadMessages(id) {
	return call('chats.messages', id).then((payload) => {
		if (state.activeChat === id) {
			state.messages = (Array.isArray(payload) ? payload : payload?.messages) || [];
			state.chatTopic = Array.isArray(payload) ? null : (payload?.topic ?? null);
			state.chatPinned = Array.isArray(payload) ? [] : (payload?.pinned ?? []);
			state.chatThreadNews = Array.isArray(payload) ? [] : (payload?.threadsWithNews ?? []);
			render();
		}
	});
}

function openChat(id) {
	if (id === null) {
		return;
	}
	if (state.activeChat !== id) {
		state.messages = [];
		resetChatThreadState();
	}
	state.activeChat = id;
	state.chatView = 'thread';
	const chat = state.chats.find((c) => c.id === id);
	if (chat && chat.unread) {
		chat.unread = 0;
		call('chats.read', id);
	}
	render();
	panel('chat')?.querySelector('.quickbar-panel-form input')?.focus();
	return loadMessages(id);
}

function backToChatList() {
	resetChatThreadState();
	state.chatView = 'list';
	render();
	// Náhled a pořadí po odeslané zprávě
	loadChats();
	panel('chat')?.querySelector('[data-quickbar-chat-query]')?.focus();
}

// Pro polling projektu: otevřený seznam načte znovu, otevřené vlákno jeho zprávy
export function refreshQuickbarChat() {
	if (!bar() || state.open !== 'chat') {
		return;
	}
	if (state.chatView === 'thread' && state.activeChat !== null) {
		refreshOpenChat();
	} else if (state.channels?.mode === 'threads') {
		loadThreads();
		loadThreadsUnread();
	} else if (state.channels?.mode === 'reminders') {
		loadReminders();
		loadRemindersCount();
	} else {
		loadChats();
	}
}

function refreshOpenChat() {
	loadMessages(state.activeChat);
	if (state.chatReplies) {
		loadReplies();
	}
}

const chatAttachments = () => source.chats?.attachments === true;

// Zpráva jde do otevřeného vlákna (parentId) a s vybranými soubory; options jen když je co předat
function sendMessage(text) {
	const chatId = state.activeChat;
	if (chatId === null || state.chatUploading) {
		return;
	}
	const replies = state.chatReplies;
	const files = chatAttachments() ? [...state.chatFiles] : [];
	if (!text && !files.length) {
		return;
	}
	const options = {
		...(replies ? { parentId: replies.messageId } : {}),
		...(files.length ? { files } : {}),
	};
	state.chatUploading = files.length > 0;
	render();
	call('chats.send', chatId, text, ...(Object.keys(options).length ? [options] : [])).then((message) => {
		if (files.length) {
			state.chatFiles = [];
		}
		if (state.activeChat !== chatId) {
			return;
		}
		const sent = message || { mine: true, text, time: '' };
		if (replies) {
			if (state.chatReplies?.messageId === replies.messageId) {
				state.chatReplies.replies.push(sent);
			}
			const parent = state.messages.find((m) => m.id === replies.messageId);
			if (parent) {
				parent.replyCount = (Number(parent.replyCount) || 0) + 1;
			}
		} else {
			state.messages.push(sent);
		}
		render();
	}).finally(() => {
		if (files.length) {
			state.chatUploading = false;
			render();
		}
	});
}

// === Vlákna odpovědí, připnuté zprávy, připomenutí, přílohy ===

function findMessage(id) {
	const replies = state.chatReplies ? [state.chatReplies.parent, ...state.chatReplies.replies] : [];
	return [...replies, ...state.messages].find((m) => m?.id != null && String(m.id) === String(id)) ?? null;
}

function openReplies(id) {
	const parent = state.messages.find((m) => m.id != null && String(m.id) === String(id));
	if (!parent) {
		return;
	}
	state.chatMenu = null;
	state.chatRemind = null;
	state.chatPinnedOpen = false;
	state.chatReplies = { messageId: parent.id, parent, replies: [] };
	render();
	focusChatInput();
	loadReplies();
}

function loadReplies() {
	const chatId = state.activeChat;
	const messageId = state.chatReplies?.messageId;
	return call('chats.thread', chatId, messageId).then((payload) => {
		const replies = state.chatReplies;
		if (state.activeChat !== chatId || replies?.messageId !== messageId) {
			return;
		}
		state.chatReplies = { messageId, parent: payload?.parent ?? replies.parent, replies: payload?.replies ?? [], following: payload?.following };
		render();
		// Načtením se vlákno označilo přečtené - počet u Chatu
		loadThreadsUnread();
	});
}

function toggleFollow() {
	const chatId = state.activeChat;
	const replies = state.chatReplies;
	if (!replies || typeof replies.following !== 'boolean') {
		return;
	}
	call('chats.follow', chatId, replies.messageId, !replies.following).then(() => {
		if (state.activeChat === chatId && state.chatReplies?.messageId === replies.messageId) {
			loadReplies();
		}
	});
}

// Pruh nad zprávami: vlákna konverzace s novými odpověďmi, klik otevře první z nich
function renderChatNews() {
	const $news = document.querySelector('[data-quickbar-chat-news]');
	if (!$news) {
		return;
	}
	const news = state.chatView === 'thread' && !state.chatReplies && !state.chatSelecting ? state.chatThreadNews : [];
	$news.hidden = !news.length;
	if (!news.length) {
		return;
	}
	const count = news.length;
	const label = (count === 1 ? $news.dataset.one : (count <= 4 ? $news.dataset.few : $news.dataset.many) ?? '').replace('%count%', count);
	setHtml($news, `
		<button type="button" class="quickbar-chat-news-button" data-quickbar-thread-news="${esc(news[0])}">
			<span class="quickbar-chat-news-dot" aria-hidden="true"></span>
			<span class="quickbar-chat-news-label">${esc(label)}</span>
			<span class="quickbar-chat-news-open">${esc($news.dataset.open)}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
		</button>`);
}

// === Sledovaná vlákna napříč konverzacemi ===

function openThreads() {
	const $channels = channelsRoot();
	if (!$channels) {
		return;
	}
	state.channels = { mode: 'threads', list: null };
	$channels.innerHTML = `${channelsHeader($channels.dataset.threads)}
		<div class="quickbar-channel-list quickbar-threads-list" data-quickbar-threads-list></div>`;
	render();
	$channels.querySelector('[data-quickbar-channels-back]').focus();
	loadThreads();
}

function loadThreads() {
	const threads = state.channels;
	return call('chats.threads').then((list) => {
		if (state.channels === threads) {
			threads.list = list || [];
			renderThreadsList();
		}
	});
}

function renderThreadsList() {
	const $list = document.querySelector('[data-quickbar-threads-list]');
	const list = state.channels?.list;
	if (!$list || !list) {
		return;
	}
	const ds = channelsRoot().dataset;
	setHtml($list, list.length
		? list.map((t) => {
			const unread = Number(t.unread) || 0;
			const meta = [repliesLabel(Number(t.replyCount) || 0), t.lastReplyTime ? threadText('lastReply').replace('%time%', () => t.lastReplyTime) : null]
				.filter(Boolean).join(' · ');
			return `
			<button type="button" class="quickbar-chat-row quickbar-thread-item ${unread ? 'quickbar-chat-row--unread' : ''}"
				data-quickbar-thread-open="${esc(t.chatId)}" data-quickbar-thread-message="${esc(t.messageId)}">
				${chatAvatar({ name: t.chatName, kind: t.kind })}
				<span class="quickbar-chat-text">
					<span class="quickbar-chat-line">
						<span class="quickbar-chat-name">${esc(t.chatName)}${t.private ? lockIcon : ''}</span>
						${unread ? `<span class="badge bg-danger">${esc(unread)}</span>` : ''}
					</span>
					<span class="quickbar-thread-item-text">${t.author ? `<strong>${esc(t.author)}:</strong> ` : ''}${esc(t.text)}</span>
					${t.lastReply ? `<span class="quickbar-chat-preview">${esc([t.lastReply.author, t.lastReply.text].filter(Boolean).join(': '))}</span>` : ''}
					<span class="quickbar-thread-item-meta">${esc(meta)}</span>
				</span>
			</button>`;
		}).join('')
		: `<div class="quickbar-empty">${esc(ds.threadsEmpty)}</div>`);
}

// Vlákno ze seznamu Vlákna: konverzace a rovnou její vlákno (rodiče doplní thread())
function openThreadFrom(chatId, messageId) {
	const find = () => state.chats.find((c) => String(c.id) === String(chatId));
	const show = () => {
		const chat = find();
		if (!chat) {
			return;
		}
		resetChannels();
		openChat(chat.id);
		const parent = state.messages.find((m) => m.id != null && String(m.id) === String(messageId)) ?? null;
		state.chatReplies = { messageId: parent?.id ?? messageId, parent, replies: [] };
		render();
		loadReplies();
	};
	find() ? show() : loadChats().then(show);
}

// === Připomenout později ===

function openReminders() {
	const $channels = channelsRoot();
	if (!$channels) {
		return;
	}
	state.channels = { mode: 'reminders', list: null };
	$channels.innerHTML = `${channelsHeader($channels.dataset.reminders)}
		<div class="quickbar-channel-list quickbar-threads-list" data-quickbar-reminders-list></div>`;
	render();
	$channels.querySelector('[data-quickbar-channels-back]').focus();
	loadReminders();
}

function loadReminders() {
	const reminders = state.channels;
	return call('chats.reminders').then((list) => {
		if (state.channels === reminders) {
			reminders.list = list || [];
			renderRemindersList();
		}
	});
}

function renderRemindersList() {
	const $list = document.querySelector('[data-quickbar-reminders-list]');
	const list = state.channels?.list;
	if (!$list || !list) {
		return;
	}
	const ds = channelsRoot().dataset;
	setHtml($list, list.length
		? list.map((r) => `
			<div class="quickbar-reminder-item${r.due ? ' quickbar-reminder-item--due' : ''}">
				<button type="button" class="quickbar-chat-row quickbar-thread-item" data-quickbar-reminder-open="${esc(r.id)}">
					${chatAvatar({ name: r.chatName, kind: r.kind })}
					<span class="quickbar-chat-text">
						<span class="quickbar-chat-line">
							<span class="quickbar-chat-name">${esc(r.chatName)}${r.private ? lockIcon : ''}</span>
						</span>
						<span class="quickbar-thread-item-text">${esc(r.text)}</span>
						${r.author ? `<span class="quickbar-chat-preview">${esc(r.author)}</span>` : ''}
						<span class="quickbar-reminder-at">${r.due ? '<span class="quickbar-chat-news-dot" aria-hidden="true"></span>' : ''}<i class="fa-solid fa-clock" aria-hidden="true"></i>${esc(r.at)}</span>
					</span>
				</button>
				${chatSourceHas('reminderDone') ? `<button type="button" class="quickbar-message-action quickbar-reminder-done" title="${esc(ds.reminderDone)}"
					aria-label="${esc(ds.reminderDone)}" data-quickbar-reminder-done="${esc(r.id)}"><i class="fa-solid fa-check"></i></button>` : ''}
			</div>`).join('')
		: `<div class="quickbar-empty">${esc(ds.remindersEmpty)}</div>`);
}

const findReminder = (id) => state.channels?.list?.find((r) => String(r.id) === String(id));

function reminderDone(id) {
	const reminder = findReminder(id);
	if (!reminder) {
		return;
	}
	call('chats.reminderDone', reminder.id).then(() => {
		if (state.channels?.mode === 'reminders') {
			loadReminders();
		}
		loadRemindersCount();
	});
}

// Připomínka: konverzace a zvýrazněná zpráva, u odpovědi rovnou vlákno se zvýrazněnou odpovědí
function openReminderFrom(reminder) {
	const find = () => state.chats.find((c) => String(c.id) === String(reminder.chatId));
	const show = () => {
		const chat = find();
		if (!chat) {
			return;
		}
		resetChannels();
		const loaded = openChat(chat.id);
		if (reminder.parentId != null) {
			const parent = state.messages.find((m) => m.id != null && String(m.id) === String(reminder.parentId)) ?? null;
			state.chatReplies = { messageId: parent?.id ?? reminder.parentId, parent, replies: [] };
			render();
			loadReplies().then(() => highlightMessage(reminder.messageId));
			return;
		}
		loaded.then(() => {
			if (state.activeChat === chat.id && !state.chatReplies) {
				highlightMessage(reminder.messageId);
			}
		});
	};
	find() ? show() : loadChats().then(show);
}

function closeReplies() {
	state.chatReplies = null;
	state.chatThreadNews = [];
	state.chatRemind = null;
	render();
	focusChatInput();
	// Počet odpovědí pod zprávou
	loadMessages(state.activeChat);
}

function renderChatPinned() {
	const $pinned = document.querySelector('[data-quickbar-chat-pinned]');
	if (!$pinned) {
		return;
	}
	const pinned = state.chatView === 'thread' && !state.chatReplies && !state.chatSelecting ? state.chatPinned : [];
	$pinned.hidden = !pinned.length;
	if (!pinned.length) {
		return;
	}
	const open = state.chatPinnedOpen;
	setHtml($pinned, `
		<button type="button" class="quickbar-chat-pinned-toggle" aria-expanded="${open}" data-quickbar-pinned-toggle>
			<i class="fa-solid fa-thumbtack"></i>
			<span class="quickbar-chat-pinned-label">${esc(($pinned.dataset.label ?? '').replace('%count%', pinned.length))}</span>
			<i class="fa-solid fa-chevron-down quickbar-chat-pinned-chevron"></i>
		</button>
		${open ? `<div class="quickbar-chat-pinned-list">${pinned.map((item) => `
			<button type="button" class="quickbar-chat-pinned-item" data-quickbar-pinned-item="${esc(item.id)}">
				${item.author || item.time ? `<span class="quickbar-chat-pinned-meta">${esc([item.author, item.time].filter(Boolean).join(' · '))}</span>` : ''}
				<span class="quickbar-chat-pinned-text">${esc(item.text)}</span>
			</button>`).join('')}</div>` : ''}`);
}

let highlightTimer = null;

function highlightMessage(id) {
	state.chatPinnedOpen = false;
	state.chatHighlight = String(id);
	render();
	const $thread = document.querySelector('[data-quickbar-thread]');
	[...($thread?.querySelectorAll('[data-quickbar-message-id]') ?? [])]
		.find((el) => el.dataset.quickbarMessageId === String(id))
		?.scrollIntoView({ block: 'center', behavior: 'smooth' });
	clearTimeout(highlightTimer);
	highlightTimer = setTimeout(() => {
		state.chatHighlight = null;
		render();
	}, 2000);
}

function togglePin(id) {
	const chatId = state.activeChat;
	const message = findMessage(id);
	if (!message) {
		return;
	}
	const pinned = !message.pinned;
	message.pinned = pinned;
	render();
	call('chats.pin', chatId, message.id, pinned).then(() => {
		if (state.activeChat === chatId) {
			refreshOpenChat();
		}
	});
}

function toggleMessageMenu(id) {
	state.chatRemind = state.chatRemind?.id === String(id) ? null : { id: String(id), view: 'menu' };
	render();
	document.querySelector('.quickbar-remind-menu .quickbar-chat-menu-item')?.focus();
}

function showRemindOptions() {
	state.chatRemind.view = 'remind';
	render();
	document.querySelector('.quickbar-remind-menu [data-quickbar-remind-when]')?.focus();
}

// Nabídka pod tečkami: pravým okrajem k tlačítku, pod ním, a když se dolů nevejde, nad ním; vždy uvnitř
// konverzace. Fixní prvek může mít za základ panel (backdrop-filter skla), proto se výsledná poloha dorovná.
function placeMessageMenu() {
	const $menu = document.querySelector('.quickbar-remind-menu');
	const $button = $menu?.parentElement?.querySelector('[data-quickbar-message-more]');
	const $thread = $menu?.closest('[data-quickbar-thread]');
	if (!$menu || !$button || !$thread) {
		return;
	}
	const button = $button.getBoundingClientRect();
	const area = $thread.getBoundingClientRect();
	const { width, height } = $menu.getBoundingClientRect();
	const gap = 4;
	const left = Math.max(area.left + gap, Math.min(button.right - width, area.right - width - gap));
	let top = button.bottom + gap;
	if (top + height > area.bottom - gap) {
		top = Math.max(area.top + gap, button.top - height - gap);
	}
	$menu.style.setProperty('--quickbar-menu-left', '0px');
	$menu.style.setProperty('--quickbar-menu-top', '0px');
	const origin = $menu.getBoundingClientRect();
	$menu.style.setProperty('--quickbar-menu-left', `${left - origin.left}px`);
	$menu.style.setProperty('--quickbar-menu-top', `${top - origin.top}px`);
}

function closeRemind() {
	state.chatRemind = null;
	render();
}

let noticeTimer = null;

function remind(when) {
	const chatId = state.activeChat;
	const message = findMessage(state.chatRemind?.id);
	closeRemind();
	if (!message) {
		return;
	}
	call('chats.remind', chatId, message.id, when).then((result) => {
		loadRemindersCount();
		if (state.activeChat !== chatId || !result?.label) {
			return;
		}
		state.chatNotice = { id: String(message.id), text: result.label };
		render();
		clearTimeout(noticeTimer);
		noticeTimer = setTimeout(() => {
			state.chatNotice = null;
			render();
		}, 4000);
	});
}

function remindCustom() {
	const $input = document.querySelector('[data-quickbar-remind-at]');
	const at = $input?.value ? new Date($input.value) : null;
	if (!at || Number.isNaN(at.getTime())) {
		$input?.focus();
		return;
	}
	remind(at.toISOString());
}

function renderChatFiles() {
	const $form = panel('chat')?.querySelector('[data-quickbar-form="chat"]');
	const $attach = $form?.querySelector('[data-quickbar-chat-attach]');
	if ($attach) {
		$attach.hidden = !chatAttachments();
		$attach.disabled = state.chatUploading;
	}
	const $submit = $form?.querySelector('[type="submit"]');
	if ($submit) {
		$submit.disabled = state.chatUploading;
	}
	const $files = document.querySelector('[data-quickbar-chat-files]');
	if (!$files) {
		return;
	}
	const files = chatAttachments() && state.chatView === 'thread' ? state.chatFiles : [];
	$files.hidden = !files.length;
	setHtml($files, files.map((file, index) => `
		<span class="quickbar-chat-file">
			<i class="${state.chatUploading ? 'fa-solid fa-spinner fa-spin' : 'fa-regular fa-file'}"></i>
			<span class="quickbar-file-name">${esc(file.name)}</span>
			<span class="quickbar-file-size">${esc(fileSize(file.size))}</span>
			${state.chatUploading ? '' : `<button type="button" class="quickbar-chat-file-remove" title="${esc($files.dataset.remove)}" aria-label="${esc($files.dataset.remove)}" data-quickbar-chat-file-remove="${index}"><i class="fa-solid fa-xmark"></i></button>`}
		</span>`).join(''));
}

// === Kanály - nový kanál a procházení místo seznamu konverzací ===

const channelsRoot = () => document.querySelector('[data-quickbar-channels]');

// Nad seznamem konverzací: Nový kanál, Vlákna a Připomenout později - v seznamu jsou jen chaty
function renderChatTools() {
	const $tools = document.querySelector('[data-quickbar-chat-tools]');
	const $list = document.querySelector('[data-quickbar-chats]');
	if (!$tools || !$list) {
		return;
	}
	const tool = (attribute, icon, label, badge = '', title = '') => `<button type="button" class="btn btn-sm btn-ghost" ${attribute}${title ? ` title="${esc(title)}"` : ''}><i class="${icon}"></i>${esc(label)}${badge}</button>`;
	const buttons = [
		chatSourceHas('createChannel') ? tool('data-quickbar-channel-new', 'fa-solid fa-square-plus', $tools.dataset.new) : '',
		chatSourceHas('browseChannels') ? tool('data-quickbar-channel-browse', 'fa-solid fa-compass', $tools.dataset.browse) : '',
		chatSourceHas('threads') ? tool('data-quickbar-threads', 'fa-solid fa-comments', $list.dataset.threads,
			state.threadsUnread ? `<span class="badge bg-danger">${esc(state.threadsUnread)}</span>` : '', $list.dataset.threadsHint) : '',
		// Počet připomínek je neutrální - v čas se ozvou notifikací samy
		chatSourceHas('reminders') ? tool('data-quickbar-reminders', 'fa-solid fa-clock', $list.dataset.reminders,
			state.remindersCount ? `<span class="badge quickbar-badge-neutral">${esc(state.remindersCount)}</span>` : '', $list.dataset.remindersHint) : '',
	].join('');
	$tools.hidden = !buttons || state.channels !== null || state.chatQuery.trim() !== '';
	setHtml($tools, buttons);
}

function renderChannels() {
	const $channels = channelsRoot();
	if (!$channels) {
		return;
	}
	$channels.hidden = state.channels === null;
	if (state.channels?.mode === 'create') {
		renderChannelMembers();
	} else if (state.channels?.mode === 'browse') {
		renderChannelList();
	} else if (state.channels?.mode === 'threads') {
		renderThreadsList();
	} else if (state.channels?.mode === 'reminders') {
		renderRemindersList();
	}
}

function channelsHeader(title) {
	const $channels = channelsRoot();
	return `<div class="quickbar-channels-header">
		<button type="button" class="btn btn-icon btn-sm btn-ghost" title="${esc($channels.dataset.back)}" aria-label="${esc($channels.dataset.back)}" data-quickbar-channels-back><i class="fa-solid fa-arrow-left"></i></button>
		<span class="quickbar-channels-title">${esc(title)}</span>
	</div>`;
}

// Kostra formuláře se vykreslí jednou - překreslení by bralo polím kurzor a rozepsaný text
function openChannelCreate() {
	const $channels = channelsRoot();
	if (!$channels) {
		return;
	}
	const ds = $channels.dataset;
	const channels = { mode: 'create', colleagues: null, members: new Set(), query: '', saving: false };
	state.channels = channels;
	$channels.innerHTML = `${channelsHeader(ds.new)}
		<form class="quickbar-channel-form noajax" data-quickbar-channel-form>
			<div class="quickbar-channel-field">
				<label class="form-label" for="quickbar-channel-name">${esc(ds.name)}</label>
				<input type="text" class="form-control form-control-sm" id="quickbar-channel-name" maxlength="80" autocomplete="off" required data-quickbar-channel-name>
			</div>
			<div class="form-check">
				<input type="checkbox" class="form-check-input" id="quickbar-channel-private" data-quickbar-channel-private>
				<label class="form-check-label" for="quickbar-channel-private">${esc(ds.private)}</label>
				<div class="quickbar-channel-hint">${esc(ds.privateHint)}</div>
			</div>
			${chatSourceHas('colleagues') ? `<div class="quickbar-channel-field">
				<span class="form-label quickbar-channel-members-label">${esc(ds.members)} <span class="badge" data-quickbar-channel-count hidden></span></span>
				<div class="quickbar-search-field">
					<i class="fa-solid fa-magnifying-glass"></i>
					<input type="text" class="form-control form-control-sm" autocomplete="off" placeholder="${esc(ds.membersSearch)}" aria-label="${esc(ds.membersSearch)}" data-quickbar-channel-query>
				</div>
				<div class="quickbar-channel-members" data-quickbar-channel-members></div>
			</div>` : ''}
			<button type="submit" class="btn btn-sm btn-primary quickbar-channel-submit">${esc(ds.create)}</button>
		</form>`;
	render();
	$channels.querySelector('[data-quickbar-channel-name]').focus();
	call('chats.colleagues').then((list) => {
		if (state.channels === channels) {
			channels.colleagues = list || [];
			renderChannelMembers();
		}
	});
}

function renderChannelMembers() {
	const channels = state.channels;
	const $members = document.querySelector('[data-quickbar-channel-members]');
	if (!$members || channels?.mode !== 'create') {
		return;
	}
	const $count = document.querySelector('[data-quickbar-channel-count]');
	$count.hidden = !channels.members.size;
	$count.textContent = String(channels.members.size);
	if (channels.colleagues === null) {
		return;
	}
	const query = normalize(channels.query.trim());
	const list = query ? channels.colleagues.filter((c) => normalize(c.name).includes(query)) : channels.colleagues;
	setHtml($members, list.length
		? list.map((c) => `
			<label class="quickbar-chat-row quickbar-channel-member">
				<input type="checkbox" class="form-check-input" data-quickbar-channel-member="${esc(c.id)}"${channels.members.has(String(c.id)) ? ' checked' : ''}>
				${chatAvatar(c, true)}
				<span class="quickbar-chat-name">${esc(c.name)}</span>
			</label>`).join('')
		: `<div class="quickbar-empty">${esc(channelsRoot().dataset.colleaguesEmpty)}</div>`);
}

function toggleChannelMember(id, checked) {
	const members = state.channels?.members;
	if (!members) {
		return;
	}
	checked ? members.add(id) : members.delete(id);
	renderChannelMembers();
}

function createChannel(form) {
	const channels = state.channels;
	const $name = form.querySelector('[data-quickbar-channel-name]');
	const name = $name.value.trim();
	if (!name) {
		$name.focus();
		return;
	}
	if (channels.saving) {
		return;
	}
	channels.saving = true;
	const $submit = form.querySelector('[type="submit"]');
	$submit.disabled = true;
	const memberIds = (channels.colleagues ?? []).filter((c) => channels.members.has(String(c.id))).map((c) => c.id);
	const isPrivate = form.querySelector('[data-quickbar-channel-private]').checked;
	call('chats.createChannel', { name, private: isPrivate, memberIds }).then((chat) => {
		if (state.channels === channels && chat) {
			showChannelChat(chat);
		}
	}).finally(() => {
		channels.saving = false;
		$submit.disabled = false;
	});
}

function openChannelBrowse() {
	const $channels = channelsRoot();
	if (!$channels) {
		return;
	}
	const channels = { mode: 'browse', list: null };
	state.channels = channels;
	$channels.innerHTML = `${channelsHeader($channels.dataset.browse)}
		<div class="quickbar-channel-list" data-quickbar-channel-list></div>`;
	render();
	$channels.querySelector('[data-quickbar-channels-back]').focus();
	call('chats.browseChannels').then((list) => {
		if (state.channels === channels) {
			channels.list = list || [];
			renderChannelList();
		}
	});
}

function renderChannelList() {
	const $list = document.querySelector('[data-quickbar-channel-list]');
	const list = state.channels?.list;
	if (!$list || !list) {
		return;
	}
	const ds = channelsRoot().dataset;
	setHtml($list, list.length
		? list.map((c) => `
			<div class="quickbar-chat-row quickbar-channel-row">
				${chatAvatar({ ...c, kind: 'channel' })}
				<span class="quickbar-chat-text">
					<span class="quickbar-chat-name">${esc(c.name)}${c.private ? lockIcon : ''}</span>
					<span class="quickbar-chat-preview">${esc((ds.membersCount ?? '').replace('%count%', c.members ?? 0))}</span>
				</span>
				${c.joined
					? `<button type="button" class="btn btn-sm btn-outline-secondary" data-quickbar-channel-open="${esc(c.id)}">${esc(ds.open)}</button>`
					: `<button type="button" class="btn btn-sm btn-primary" data-quickbar-channel-join="${esc(c.id)}">${esc(ds.join)}</button>`}
			</div>`).join('')
		: `<div class="quickbar-empty">${esc(ds.empty)}</div>`);
}

function joinChannel(id) {
	const channels = state.channels;
	const channel = channels?.list?.find((c) => String(c.id) === id);
	if (!channel) {
		return;
	}
	call('chats.joinChannel', channel.id).then((chat) => {
		if (state.channels === channels && chat) {
			showChannelChat(chat);
		}
	});
}

function openChannel(id) {
	const find = () => state.chats.find((c) => String(c.id) === id);
	resetChannels();
	if (find()) {
		openChat(find().id);
		return;
	}
	loadChats().then(() => openChat(find()?.id ?? null));
}

function showChannelChat(chat) {
	resetChannels();
	state.chats = [chat, ...state.chats.filter((c) => c.id !== chat.id)];
	openChat(chat.id);
}

function resetChannels() {
	state.channels = null;
	const $channels = channelsRoot();
	if ($channels) {
		$channels.innerHTML = '';
	}
}

function closeChannels() {
	resetChannels();
	render();
	panel('chat')?.querySelector('[data-quickbar-chat-query]')?.focus();
}

// === AI asistent ===

const aiRoot = () => document.querySelector('[data-quickbar-ai]');
// Instanci widgetu na element věší aiChat.js; bez něj (projekt AI nemá) zůstane panel prázdný
const aiWidget = () => state.ai.element?.adtAiChat ?? null;

// Bez limitu by fetch po uspání počítače nebo výpadku sítě visel, dokud ho prohlížeč nevzdá (minuty)
const AI_REQUEST_TIMEOUT_MS = 15000;

function aiRequest(url) {
	return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, signal: AbortSignal.timeout(AI_REQUEST_TIMEOUT_MS) })
		.then((response) => (response.ok ? response.json() : Promise.reject(response)));
}

/**
 * AI asistent s kurzorem v poli zprávy a konverzací na konci - otevře panel, je-li zavřený.
 * Pro klávesové zkratky projektu; pevnou zkratku Ctrl+Alt+Shift+A (DICTATION_CHORD) obsluhuje
 * lišta sama.
 */
export function openQuickbarAi() {
	if (!aiRoot()) {
		return;
	}
	state.open === 'ai' ? focusAi() : open('ai');
}

function openAi(focus = true) {
	const $root = aiRoot();
	if (!$root) {
		return;
	}
	state.ai.view = 'chat';

	if (state.ai.config) {
		render();
		if (focus) {
			focusAi();
		}
		return;
	}
	if (state.ai.loading) {
		render();
		return;
	}

	const $chat = document.querySelector('[data-quickbar-ai-chat]');
	$chat.innerHTML = `<div class="quickbar-empty">${esc($chat.dataset.loading)}</div>`;
	// Výměna lišty (nová stránka) načítání zahodí - jeho výsledek už nepatří do nového panelu
	const loading = state.ai.loading = {};
	const current = () => state.ai.loading === loading;

	// Po otevření vždy poslední konverzace, bez ní prázdný stav
	aiRequest($root.dataset.quickbarAi)
		.then((payload) => {
			if (!current()) {
				return Promise.reject(loading);
			}
			state.ai.config = payload.chat;
			state.ai.historyUrl = payload.historyUrl;
			state.ai.share = payload.shareUrl ? { url: payload.shareUrl, param: payload.shareParam } : null;
			return loadAiConversations();
		})
		.then(() => {
			if (!current()) {
				return;
			}
			state.ai.loading = null;
			// Po obnovení stránky konverzace, která byla otevřená (pokud ještě existuje)
			const restore = state.ai.restore;
			state.ai.restore = null;
			const restoredId = restore && state.ai.conversations.some((c) => String(c.id) === String(restore.conversationId)) ? restore.conversationId : null;
			// Povel „nový chat“ z ADTalk přišel dřív, než se AI načetla
			mountAi(state.ai.startNew ? null : (restoredId ?? state.ai.conversations[0]?.id ?? null));
			state.ai.startNew = false;
			if (restore?.full) {
				state.ai.full = true;
			}
			render();
			if (focus) {
				focusAi();
			} else {
				// Bez fokusu jen konverzaci na konec
				setTimeout(() => aiWidget()?.scrollToBottom(), 50);
			}
		})
		.catch(() => {
			if (!current()) {
				return;
			}
			state.ai.loading = null;
			// Config bez připojeného widgetu by další otevření bralo jako načtenou AI a panel by
			// zůstal prázdný bez pole zprávy až do obnovení stránky
			state.ai.config = null;
			$chat.innerHTML = `<div class="quickbar-empty">${esc($chat.dataset.unavailable)}</div>`;
		});
}

// Po návratu sítě se otevřený panel, kterému načtení selhalo, načte sám
window.addEventListener('online', () => {
	if (state.open === 'ai' && !state.ai.config && !state.ai.loading) {
		openAi(false);
	}
});

function loadAiConversations() {
	return aiRequest(state.ai.config.urls.conversations).then((payload) => {
		state.ai.conversations = payload.conversations || [];
		// Smazané konverzace z nepřečtených vypadnou, jinak by počet na liště nezmizel
		const ids = new Set(state.ai.conversations.map((c) => String(c.id)));
		state.ai.unread = new Set([...state.ai.unread].filter((id) => ids.has(id)));
		state.ai.conversations.filter((c) => c.unread).forEach((c) => state.ai.unread.add(String(c.id)));
		saveAiUnread();
		render();
	});
}

// === AI - nepřečtené odpovědi ===

function loadAiUnread() {
	try {
		return new Set(JSON.parse(sessionStorage.getItem(AI_UNREAD_KEY) || '[]'));
	} catch {
		return new Set();
	}
}

// Přes navigaci mezi stránkami; trvale je drží až backend (pole `unread` konverzace)
function saveAiUnread() {
	try {
		sessionStorage.setItem(AI_UNREAD_KEY, JSON.stringify([...state.ai.unread]));
	} catch {
		// Bez sessionStorage zůstanou nepřečtené jen do konce stránky
	}
}

function isAiVisible(conversationId) {
	return state.open === 'ai' && (state.ai.view === 'chat' || aiFull()) && document.visibilityState === 'visible'
		&& String(state.ai.conversationId) === String(conversationId);
}

// Konverzace, kterou uživatel právě vidí, je přečtená
function readVisibleAi() {
	const id = String(state.ai.conversationId);
	if (state.ai.unread.has(id) && isAiVisible(id)) {
		state.ai.unread.delete(id);
		saveAiUnread();
	}
}

function mountAi(conversationId) {
	destroyAi();
	state.ai.conversationId = conversationId;
	const element = document.createElement('div');
	element.className = 'quickbar-ai-widget';
	element.dataset.adtFancyadminAiChat = JSON.stringify({ ...state.ai.config, conversationId });
	document.querySelector('[data-quickbar-ai-chat]').replaceChildren(element);
	state.ai.element = element;
	renderAiHeader();
}

// Odpojený widget by jinak dál dotazoval server na odpověď
function destroyAi() {
	state.ai.element?.adtAiChat?.destroy();
	state.ai.element = null;
}

function focusAi() {
	// Widget se připojuje až v MutationObserveru aiChat.js. Pole zprávy podle třídy - v konverzaci
	// s odpovědí je před ním v DOM skryté pole komentáře k hodnocení
	setTimeout(() => {
		document.querySelector('[data-quickbar-ai-chat] .ai-chat__textarea')?.focus();
		// Konverzace se načetla do ještě skrytého nebo užšího panelu - na konec až teď
		aiWidget()?.scrollToBottom();
	}, 50);
}

function openAiConversation(conversationId) {
	state.ai.view = 'chat';
	state.ai.conversationId = conversationId;
	const widget = aiWidget();
	if (widget) {
		conversationId === null ? widget.newConversation() : widget.loadConversation(conversationId);
	} else if (state.ai.config) {
		mountAi(conversationId);
	}
	render();
	focusAi();
}

function toggleAiFull() {
	if (!state.ai.config) {
		return;
	}
	state.ai.full = !state.ai.full;
	// Na celé ploše je seznam konverzací vlevo, přehled nedávných tam není
	state.ai.view = 'chat';
	render();
	if (state.ai.full) {
		loadAiConversations();
	}
	focusAi();
}

function toggleAiRecent() {
	if (!state.ai.config) {
		return;
	}
	state.ai.view = state.ai.view === 'recent' ? 'chat' : 'recent';
	render();
	if (state.ai.view === 'recent') {
		loadAiConversations();
	} else {
		focusAi();
	}
}

function formatAiTime(value) {
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return '';
	}
	const locale = document.documentElement.lang || undefined;
	return date.toDateString() === new Date().toDateString()
		? date.toLocaleTimeString(locale, { hour: 'numeric', minute: '2-digit' })
		: date.toLocaleDateString(locale, { day: 'numeric', month: 'numeric' });
}

function aiConversationRow(c, $list, icon = true) {
	const current = String(c.id) === String(state.ai.conversationId);
	const unread = state.ai.unread.has(String(c.id));
	return `<button type="button" class="quickbar-ai-conversation ${current ? 'quickbar-ai-conversation--current' : ''} ${unread ? 'quickbar-ai-conversation--unread' : ''}" data-quickbar-ai-conversation="${esc(c.id)}">
		${icon ? '<i class="fa-regular fa-message"></i>' : ''}
		<span class="quickbar-ai-conversation-text">
			<span class="quickbar-ai-conversation-head">
				<span class="quickbar-ai-conversation-title">${esc(c.title || $list.dataset.untitled)}</span>
				${unread ? `<span class="quickbar-unread-dot" title="${esc($list.dataset.unread)}"></span>` : ''}
				<span class="quickbar-ai-conversation-time">${esc(formatAiTime(c.updated))}</span>
			</span>
			${c.preview ? `<span class="quickbar-ai-conversation-preview">${esc(c.preview)}</span>` : ''}
		</span>
	</button>`;
}

function renderAi() {
	const $root = aiRoot();
	if (!$root) {
		return;
	}
	const recent = state.ai.view === 'recent';
	$root.classList.toggle('quickbar-ai--recent', recent);
	document.querySelectorAll('[data-quickbar-ai-recent-toggle].btn').forEach((button) => {
		button.classList.toggle('btn-primary', recent);
		button.classList.toggle('btn-glass', !recent);
	});

	const $list = $root.querySelector('[data-quickbar-ai-list]');
	const conversations = state.ai.conversations;
	setHtml($list, conversations.length
		? conversations.map((c) => aiConversationRow(c, $list)).join('')
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`);

	$root.querySelectorAll('[data-quickbar-ai-all]').forEach(($all) => {
		$all.hidden = !conversations.length || !state.ai.historyUrl;
		if (state.ai.historyUrl) {
			$all.href = state.ai.historyUrl;
		}
	});

	renderAiSidebar($root);
	renderAiHeader();
}

// Skupina podle data poslední změny: dnes, včera, tento týden (od pondělí), starší
function aiGroupKey(value) {
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return 'older';
	}
	const day = 24 * 60 * 60 * 1000;
	const today = new Date();
	today.setHours(0, 0, 0, 0);
	const monday = today.getTime() - ((today.getDay() + 6) % 7) * day;
	if (date >= today) {
		return 'today';
	}
	if (date >= today.getTime() - day) {
		return 'yesterday';
	}
	return date >= monday ? 'week' : 'older';
}

function renderAiSidebar($root) {
	const $list = $root.querySelector('[data-quickbar-ai-groups]');
	if (!$list) {
		return;
	}
	const query = normalize(state.ai.query.trim());
	const conversations = state.ai.conversations.filter((c) => !query || normalize(`${c.title} ${c.preview ?? ''}`).includes(query));
	if (!conversations.length) {
		const text = query ? $list.dataset.noResults.replace('%query%', state.ai.query.trim()) : $list.dataset.empty;
		setHtml($list, `<div class="quickbar-empty">${esc(text)}</div>`);
		return;
	}
	const groups = ['today', 'yesterday', 'week', 'older']
		.map((key) => ({ key, items: conversations.filter((c) => aiGroupKey(c.updated) === key) }))
		.filter((group) => group.items.length);
	const $rows = $root.querySelector('[data-quickbar-ai-list]');
	setHtml($list, groups.map((group) => `
		<div class="quickbar-group-title">${esc($list.dataset[group.key])}</div>
		${group.items.map((c) => aiConversationRow(c, $rows, false)).join('')}
	`).join(''));
}

function renderAiHeader() {
	const $panel = panel('ai');
	const $title = $panel?.querySelector('[data-quickbar-title]');
	if (!$title) {
		return;
	}
	$panel.querySelector('[data-quickbar-ai-copy-link]').hidden = !aiConversationLink();
	// Na celé ploše je nadpisem název konverzace
	const conversation = state.ai.conversations.find((c) => String(c.id) === String(state.ai.conversationId));
	const $rows = $panel.querySelector('[data-quickbar-ai-list]');
	$title.textContent = aiFull() ? (conversation?.title || $rows.dataset.untitled) : $title.dataset.quickbarTitle;

	renderAiSpeak();
	const $full = $panel.querySelector('[data-quickbar-ai-full]');
	$full.title = state.ai.full ? $full.dataset.shrink : $full.dataset.expand;
	$full.querySelector('i').className = state.ai.full ? 'fa-solid fa-down-left-and-up-right-to-center' : 'fa-solid fa-up-right-and-down-left-from-center';
}

/** Odkaz na otevřenou konverzaci - stránka AI ji po načtení otevře; null = žádná není */
function aiConversationLink() {
	if (!state.ai.share || !state.ai.conversationId || state.ai.view !== 'chat') {
		return null;
	}
	const url = new URL(state.ai.share.url, window.location.href);
	url.searchParams.set(state.ai.share.param, state.ai.conversationId);
	return url.toString();
}

function copyAiLink($button) {
	const link = aiConversationLink();
	if (!link) {
		return;
	}
	// Schránka jen na HTTPS - jinde odkaz aspoň ukázat ke zkopírování
	if (!navigator.clipboard) {
		window.prompt($button.title, link);
		return;
	}
	navigator.clipboard.writeText(link).then(() => {
		const $icon = $button.querySelector('i');
		const title = $button.title;
		$icon.className = 'fa-solid fa-check';
		$button.title = $button.dataset.copied;
		setTimeout(() => {
			$icon.className = 'fa-solid fa-link';
			$button.title = title;
		}, 1500);
	}, () => window.prompt($button.title, link));
}

/**
 * Tlačítko předčítání: zapnuto = aktivní (btn-primary), při čtení je to Zastavit. Čte aplikace
 * ADTalk - když neběží, zapnuté tlačítko ukazuje přeškrtnutý reproduktor a v popisku proč.
 */
function renderAiSpeak() {
	const $button = panel('ai')?.querySelector('[data-quickbar-ai-speak]');
	if (!$button) {
		return;
	}
	const enabled = speech.isEnabled();
	const speaking = speech.isSpeaking();
	const missing = enabled && speech.isAvailable() === false;
	$button.classList.toggle('btn-primary', enabled);
	$button.classList.toggle('btn-glass', !enabled);
	$button.title = speaking ? $button.dataset.stop : (missing ? $button.dataset.unavailable : (enabled ? $button.dataset.off : $button.dataset.on));
	$button.querySelector('i').className = speaking ? 'fa-solid fa-stop'
		: (missing ? 'fa-solid fa-volume-off' : (enabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark'));
	// Zapnuté předčítání ověří, že ADTalk běží (výsledek si modul chvíli pamatuje)
	if (enabled && !speaking) {
		const before = speech.isAvailable();
		speech.checkAvailable().then((now) => now !== before && renderAiSpeak());
	}
}

function toggleAiSpeak() {
	// Během čtení tlačítko jen zastaví, předčítání zůstane zapnuté
	if (speech.isSpeaking()) {
		speech.stop();
	} else {
		speech.setEnabled(!speech.isEnabled());
		// Po zapnutí hned zjistit, jestli ADTalk běží (mezitím ho šlo spustit)
		if (speech.isEnabled()) {
			speech.checkAvailable(true).then(renderAiSpeak);
		}
	}
	renderAiSpeak();
}

function stopAiSpeech() {
	if (!speech.isSpeaking()) {
		return false;
	}
	speech.stop();
	renderAiSpeak();

	return true;
}

// Nová odpověď v liště se se zapnutým předčítáním přečte nahlas (i do zavřeného panelu)
document.addEventListener('ai-chat:reply', (e) => {
	if (!e.target.closest?.('[data-quickbar-ai-chat]') || !speech.isEnabled()) {
		return;
	}
	speech.speakReply(e.detail.element, renderAiSpeak).then(renderAiSpeak);
});

// Widget hlásí odeslání (ještě čeká na odpověď) i doručenou odpověď
document.addEventListener('ai-chat:conversation', (e) => {
	const id = e.detail?.conversation?.id;
	if (!e.target.closest?.('[data-quickbar-ai-chat]') || !id) {
		return;
	}
	state.ai.conversationId = id;
	// První zpráva nové konverzace - teprve teď na ni jde odkaz
	renderAiHeader();
	// Nová konverzace se má hned objevit v seznamu na celé ploše
	if (e.detail.created && aiFull()) {
		loadAiConversations();
	}
	if (aiWidget()?.loading) {
		state.ai.waiting.add(String(id));
		return;
	}
	// Odpověď dorazila, když okno nebylo vidět (zavřené, přehled nedávných)
	if (state.ai.waiting.delete(String(id)) && !isAiVisible(id)) {
		state.ai.unread.add(String(id));
		saveAiUnread();
		render();
	}
});

// Stránka, kterou AI otevřela (nástroj s výsledkem navigate) nebo na kterou uživatel klikl
// v odpovědi, se načte ajaxem za lištou - lišta je mimo snippet body, chat zůstane otevřený.
// Odpověď, která dorazí do zavřeného panelu, nikam nepřesouvá.
document.addEventListener('ai-chat:navigate', (e) => {
	if (!e.target.closest?.('[data-quickbar-ai-chat]')) {
		return;
	}
	if (e.detail.auto && state.open !== 'ai') {
		return;
	}
	e.preventDefault();
	// Odkaz jako původce požadavku - adresa se zapíše do historie jako po kliku (naja.js)
	const link = document.createElement('a');
	link.href = e.detail.url;
	naja.uiHandler.clickElement(link).catch(() => {});
});

// Pevné zkratky diktování: aplikace ADTalk (adt/adtalk) pošle Ctrl+Alt+Shift+A a přepis pak
// napíše do pole; povel „nový chat“ je Ctrl+Alt+Shift+N - funguje v každém projektu s AI v liště
const DICTATION_CHORD = { ctrlKey: true, altKey: true, shiftKey: true, metaKey: false };

// Nadiktovaná zpráva míří do konverzace, která byla zrovna otevřená - o tom, jestli na ni
// navazuje, nebo začne novou, rozhodne server (adt/ai-chat ConversationRouter). ADTalk pošle
// zkratku znovu těsně před napsáním přepisu, zpráva tedy odchází do vteřiny; krátké okno
// nechá psanou zprávu po povelu bez textu (zastav, zopakuj) tam, kam ji uživatel napsal.
const DICTATION_SEND_MS = 15000;

document.addEventListener('keydown', (e) => {
	if (e.repeat || !aiRoot() || !['KeyA', 'KeyN'].includes(e.code) || Object.entries(DICTATION_CHORD).some(([key, value]) => e[key] !== value)) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	// Nový povel - předčítání předchozí odpovědi by se pletlo do nahrávání
	stopAiSpeech();
	if (e.code === 'KeyN') {
		newAiConversation();
		return;
	}
	state.ai.dictatedAt = Date.now();
	openQuickbarAi();
}, true);

document.addEventListener('ai-chat:before-send', (e) => {
	if (!e.target.closest?.('[data-quickbar-ai-chat]') || Date.now() - state.ai.dictatedAt > DICTATION_SEND_MS) {
		return;
	}
	state.ai.dictatedAt = 0;
	e.detail.body.append('routeConversation', '1');
});

function newAiConversation() {
	if (!state.ai.config) {
		// AI se teprve načte - místo poslední konverzace prázdná
		state.ai.startNew = true;
		openQuickbarAi();
		return;
	}
	if (state.open !== 'ai') {
		open('ai');
	}
	openAiConversation(null);
}

document.addEventListener('visibilitychange', () => {
	if (document.visibilityState === 'visible' && bar()) {
		render();
	}
});

// === Menu (jen mobil) ===

function toggleMenuSub(button) {
	const expand = button.getAttribute('aria-expanded') !== 'true';
	// Otevřené je vždy jen jedno podmenu, profil se rozbaluje nezávisle
	if (expand && button.dataset.quickbarMenuToggle !== 'user') {
		button.closest('[data-quickbar-menu]').querySelectorAll('[data-quickbar-menu-toggle=""][aria-expanded="true"]').forEach((other) => setMenuSub(other, false));
	}
	setMenuSub(button, expand);
	if (expand) {
		revealMenuSub(button);
	}
}

function setMenuSub(button, expanded) {
	button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
	button.nextElementSibling.hidden = !expanded;
}

// Posune obsah okna, aby bylo rozbalené podmenu celé vidět; vyšší podmenu nechá rodiče nahoře
function revealMenuSub(button) {
	requestAnimationFrame(() => requestAnimationFrame(() => {
		const box = button.closest('[data-quickbar-menu]');
		const boxRect = box.getBoundingClientRect();
		const buttonRect = button.getBoundingClientRect();
		const overflow = button.nextElementSibling.getBoundingClientRect().bottom + 8 - boxRect.bottom;
		if (overflow > 0) {
			box.scrollTo({ top: box.scrollTop + Math.min(overflow, buttonRect.top - boxRect.top - 8), behavior: 'smooth' });
		}
	}));
}

// Odkaz menu si naviguje sám (naja.js), okno se jen zavře
document.addEventListener('click', (e) => {
	if (e.target.closest?.('[data-quickbar-menu] a[href]')) {
		close();
	}
}, true);

// Menu na desktopu není, ostatní okna zůstanou otevřená
mobileQuery.addEventListener('change', () => {
	if (!bar()) {
		return;
	}
	if (!mobileQuery.matches && state.open === 'menu') {
		close();
	} else {
		render();
	}
});

// === Linky panelů podle posunu (stejně jako boční panel) ===

let measureScheduled = false;

function scheduleMeasure() {
	if (!measureScheduled) {
		measureScheduled = true;
		requestAnimationFrame(measurePanel);
	}
}

function measurePanel() {
	measureScheduled = false;
	const $panel = state.open && panel(state.open);
	if (!$panel) {
		return;
	}
	// Měří se největší posuvná část, která obsahem opravdu přetéká
	const scroller = [...$panel.querySelectorAll(PANEL_SCROLLERS)]
		.filter((el) => el.clientHeight > 0 && el.scrollHeight > el.clientHeight + 1)
		.sort((a, b) => b.clientHeight - a.clientHeight)[0];
	$panel.classList.toggle('quickbar-panel--scrolled', !!scroller && scroller.scrollTop > 0);
	$panel.classList.toggle('quickbar-panel--more', !!scroller && scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight > 1);

	// Linka nad odkazem pod seznamem konverzací AI, dokud seznam pokračuje
	const $sidebarList = $panel.querySelector('.quickbar-ai-sidebar-list');
	if ($sidebarList) {
		$sidebarList.parentElement.classList.toggle('quickbar-ai-sidebar--more', $sidebarList.scrollHeight - $sidebarList.scrollTop - $sidebarList.clientHeight > 1);
	}
}

// scroll nebublá, proto zachycení na dokumentu
document.addEventListener('scroll', (e) => {
	if (e.target.closest?.('[data-quickbar-panel]')) {
		scheduleMeasure();
		// Nabídka u zprávy je fixní - jede s konverzací
		if (state.chatRemind !== null) {
			placeMessageMenu();
		}
	}
}, true);

window.addEventListener('resize', () => {
	scheduleMeasure();
	placeMessageMenu();
});

// Obsah panelů se mění i mimo render() - widget AI přidává zprávy, polling projektu vlákno chatu
let panelObserver = null;

function observePanels() {
	panelObserver?.disconnect();
	panelObserver = new MutationObserver(scheduleMeasure);
	document.querySelectorAll('[data-quickbar-panel]').forEach((el) => panelObserver.observe(el, { childList: true, subtree: true }));
}

// === Události (delegace na document - menu lišty je snippet a překresluje se) ===

document.addEventListener('click', (e) => {
	if (!bar()) {
		return;
	}
	const target = e.target;

	if (target.closest('[data-quickbar-close]')) {
		close();
		return;
	}
	const toggleButton = target.closest('[data-quickbar-toggle]');
	if (toggleButton) {
		toggle(toggleButton.dataset.quickbarToggle);
		return;
	}
	if (target.closest('[data-quickbar-ai-speak]')) {
		toggleAiSpeak();
		return;
	}
	const copyLinkButton = target.closest('[data-quickbar-ai-copy-link]');
	if (copyLinkButton) {
		copyAiLink(copyLinkButton);
		return;
	}
	if (target.closest('[data-quickbar-ai-new]')) {
		openAiConversation(null);
		return;
	}
	if (target.closest('[data-quickbar-ai-recent-toggle]')) {
		toggleAiRecent();
		return;
	}
	if (target.closest('[data-quickbar-ai-full]')) {
		toggleAiFull();
		return;
	}
	// Vymazání pole hledání - input vyvolá obnovu seznamu stejně jako psaní
	const clearButton = target.closest('[data-quickbar-clear]');
	if (clearButton) {
		const input = clearButton.closest('.quickbar-search-field').querySelector('input');
		input.value = '';
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.focus();
		return;
	}
	const menuToggle = target.closest('[data-quickbar-menu-toggle]');
	if (menuToggle) {
		toggleMenuSub(menuToggle);
		return;
	}
	if (target.closest('[data-quickbar-chat-back]')) {
		backToChatList();
		return;
	}
	if (target.closest('[data-quickbar-replies-back]')) {
		closeReplies();
		return;
	}
	if (state.chatRemind !== null && !target.closest('.quickbar-remind-menu, [data-quickbar-message-more]')) {
		closeRemind();
	}
	if (chatClick(target)) {
		return;
	}
	if (state.chatMenu !== null && !target.closest('[data-quickbar-chat-menu], [data-quickbar-chat-plus]')) {
		closeChatMenu();
	}
	if (target.closest('[data-quickbar-chat-plus]')) {
		toggleChatMenu();
		return;
	}
	const menuItem = target.closest('[data-quickbar-chat-menu-item]');
	if (menuItem) {
		runChatMenuItem(menuItem.dataset.quickbarChatMenuItem);
		return;
	}
	if (target.closest('[data-quickbar-chat-topic-clear]')) {
		clearChatTopic();
		return;
	}
	const selectableMessage = target.closest('[data-quickbar-message]');
	if (selectableMessage) {
		e.preventDefault();
		toggleMessageSelected(selectableMessage.dataset.quickbarMessage);
		return;
	}
	const selectionAction = target.closest('[data-quickbar-selection-action]');
	if (selectionAction) {
		runSelectionAction(selectionAction.dataset.quickbarSelectionAction);
		return;
	}
	if (target.closest('[data-quickbar-selection-cancel]')) {
		stopSelecting();
		return;
	}
	if (target.closest('[data-quickbar-picker-close]')) {
		closePicker(null);
		return;
	}
	const pickerItem = target.closest('[data-quickbar-picker-item]');
	if (pickerItem && state.picker) {
		closePicker(pickerItems()[Number(pickerItem.dataset.quickbarPickerItem)]);
		return;
	}
	// Štítek a téma konverzace navigují samy (naja.js); na mobilu panel stránku překrývá, tak se zavře
	if (target.closest('.quickbar-message-tag[href], .quickbar-chat-topic-name[href]')) {
		if (mobileQuery.matches) {
			close();
		}
		return;
	}
	const aiConversation = target.closest('[data-quickbar-ai-conversation]');
	if (aiConversation) {
		const conversation = state.ai.conversations.find((c) => String(c.id) === aiConversation.dataset.quickbarAiConversation);
		openAiConversation(conversation ? conversation.id : null);
		return;
	}
	if (target.closest('[data-quickbar-read-all]')) {
		readAllNotifications();
		return;
	}
	const notification = target.closest('[data-quickbar-notification]');
	if (notification) {
		readNotification(notification.dataset.quickbarNotification);
		// Odkaz naviguje sám (naja.js), lišta se jen zavře
		if (notification.matches('a[href]')) {
			close();
		}
		return;
	}
	const chatRow = target.closest('[data-quickbar-chat]');
	if (chatRow) {
		const chat = state.chats.find((c) => String(c.id) === chatRow.dataset.quickbarChat);
		openChat(chat ? chat.id : null);
		return;
	}
	// Odkaz ve zprávě chatu naviguje sám (naja.js), lišta se jen zavře
	if (target.closest('.quickbar-bubble-link')) {
		close();
		return;
	}
	const result = target.closest('[data-quickbar-result]');
	if (result) {
		const item = resultItems[Number(result.dataset.quickbarResult)];
		if (item?.panel) {
			e.preventDefault();
			state.query = '';
			searchInput().value = '';
			open(item.panel);
		} else {
			// Odkaz si naviguje sám, lišta se jen zavře
			close();
		}
	}
});

// Kanály, vlákna, připnuté zprávy, připomenutí a přílohy; true = klik obsloužen
function chatClick(target) {
	const handlers = [
		['[data-quickbar-channel-new]', () => openChannelCreate()],
		['[data-quickbar-channel-browse]', () => openChannelBrowse()],
		['[data-quickbar-channels-back]', () => closeChannels()],
		['[data-quickbar-threads]', () => openThreads()],
		['[data-quickbar-reminders]', () => openReminders()],
		['[data-quickbar-reminder-done]', (el) => reminderDone(el.dataset.quickbarReminderDone)],
		['[data-quickbar-reminder-open]', (el) => {
			const reminder = findReminder(el.dataset.quickbarReminderOpen);
			if (reminder) {
				openReminderFrom(reminder);
			}
		}],
		['[data-quickbar-thread-open]', (el) => {
			// Id ze zdroje, ne z data atributu - zdroj je dostane zpátky ve stejném typu
			const t = state.channels?.list?.find((item) => String(item.chatId) === el.dataset.quickbarThreadOpen
				&& String(item.messageId) === el.dataset.quickbarThreadMessage);
			if (t) {
				openThreadFrom(t.chatId, t.messageId);
			}
		}],
		['[data-quickbar-thread-news]', (el) => openReplies(el.dataset.quickbarThreadNews)],
		['[data-quickbar-thread-follow]', () => toggleFollow()],
		['[data-quickbar-channel-join]', (el) => joinChannel(el.dataset.quickbarChannelJoin)],
		['[data-quickbar-channel-open]', (el) => openChannel(el.dataset.quickbarChannelOpen)],
		['[data-quickbar-message-more]', (el) => toggleMessageMenu(el.dataset.quickbarMessageMore)],
		['[data-quickbar-reply]', (el) => {
			closeRemind();
			openReplies(el.dataset.quickbarReply);
		}],
		['[data-quickbar-replies]', (el) => openReplies(el.dataset.quickbarReplies)],
		['[data-quickbar-pin]', (el) => {
			closeRemind();
			togglePin(el.dataset.quickbarPin);
		}],
		['[data-quickbar-remind]', () => showRemindOptions()],
		['[data-quickbar-remind-when]', (el) => {
			if (el.dataset.quickbarRemindWhen !== 'custom') {
				remind(el.dataset.quickbarRemindWhen);
				return;
			}
			state.chatRemind.view = 'custom';
			render();
			document.querySelector('[data-quickbar-remind-at]')?.focus();
		}],
		['[data-quickbar-remind-set]', () => remindCustom()],
		['[data-quickbar-pinned-toggle]', () => {
			state.chatPinnedOpen = !state.chatPinnedOpen;
			render();
		}],
		['[data-quickbar-pinned-item]', (el) => highlightMessage(el.dataset.quickbarPinnedItem)],
		['[data-quickbar-chat-attach]', () => panel('chat')?.querySelector('[data-quickbar-chat-file]')?.click()],
		['[data-quickbar-chat-file-remove]', (el) => {
			state.chatFiles.splice(Number(el.dataset.quickbarChatFileRemove), 1);
			render();
			focusChatInput();
		}],
	];
	for (const [selector, handler] of handlers) {
		const el = target.closest(selector);
		if (el) {
			handler(el);
			return true;
		}
	}
	return false;
}

// Najetí myší vybere výsledek jako šipky, jinak by byly označené dva řádky. Jen skutečný pohyb:
// posun seznamu šipkami pod stojící myší výběr přebít nesmí.
let lastPointer = null;

document.addEventListener('mousemove', (e) => {
	const pointer = `${e.screenX},${e.screenY}`;
	if (pointer === lastPointer) {
		return;
	}
	lastPointer = pointer;
	const result = e.target.closest?.('[data-quickbar-result]');
	if (result && Number(result.dataset.quickbarResult) !== state.selected) {
		selectResult(Number(result.dataset.quickbarResult), false);
	}
});

document.addEventListener('change', (e) => {
	const checkbox = e.target.closest('[data-quickbar-todo]');
	if (checkbox) {
		setTodoDone(checkbox.dataset.quickbarTodo, checkbox.checked);
		return;
	}
	const member = e.target.closest('[data-quickbar-channel-member]');
	if (member) {
		toggleChannelMember(member.dataset.quickbarChannelMember, member.checked);
		return;
	}
	if (e.target.matches('[data-quickbar-chat-file]')) {
		state.chatFiles.push(...e.target.files);
		e.target.value = '';
		render();
		focusChatInput();
	}
});

document.addEventListener('input', (e) => {
	if (e.target.matches('[data-quickbar-chat-query]')) {
		state.chatQuery = e.target.value;
		renderChat();
		return;
	}
	if (e.target.matches('[data-quickbar-channel-query]') && state.channels) {
		state.channels.query = e.target.value;
		renderChannelMembers();
		return;
	}
	if (e.target.matches('[data-quickbar-picker-query]') && state.picker) {
		searchPicker(e.target.value);
		return;
	}
	if (e.target.matches('[data-quickbar-ai-query]')) {
		state.ai.query = e.target.value;
		renderAi();
		return;
	}
	if (e.target.matches('[data-quickbar-search]')) {
		state.query = e.target.value;
		if (state.open !== 'search') {
			open('search');
		} else {
			search(state.query);
		}
	}
});

document.addEventListener('focusin', (e) => {
	if (e.target.matches('[data-quickbar-search]') && state.open !== 'search') {
		open('search');
	}
});

document.addEventListener('submit', (e) => {
	const channelForm = e.target.closest('[data-quickbar-channel-form]');
	const form = e.target.closest('[data-quickbar-form]');
	if (!form && !channelForm) {
		return;
	}
	// Formuláře lišty nikam neodesílají - jinak by je odeslal ajax (naja.js)
	e.preventDefault();
	e.stopImmediatePropagation();
	if (channelForm) {
		createChannel(channelForm);
		return;
	}
	const input = form.querySelector('input:not([type="file"])');
	const text = input.value.trim();
	if (form.dataset.quickbarForm === 'todo') {
		if (text) {
			input.value = '';
			addTodo(text);
		}
		return;
	}
	// Zpráva může být jen přílohy bez textu
	if ((!text && !(chatAttachments() && state.chatFiles.length)) || state.chatUploading) {
		return;
	}
	input.value = '';
	sendMessage(text);
}, true);

document.addEventListener('keydown', (e) => {
	if (!bar()) {
		return;
	}
	// Esc nejdřív zastaví předčítání, okno nechá
	if (e.key === 'Escape' && stopAiSpeech()) {
		e.preventDefault();
		return;
	}
	if (e.key === 'Escape' && state.open !== null) {
		e.preventDefault();
		// V podstavech vrací Esc nejdřív zpět, až pak zavře okno
		if (state.open === 'chat' && state.picker) {
			closePicker(null);
		} else if (state.open === 'chat' && state.chatMenu !== null) {
			closeChatMenu(true);
		} else if (state.open === 'chat' && state.chatRemind !== null) {
			closeRemind();
		} else if (state.open === 'chat' && state.chatSelecting) {
			stopSelecting();
		} else if (state.open === 'chat' && state.chatView === 'thread' && state.chatReplies) {
			closeReplies();
		} else if (state.open === 'chat' && state.chatView === 'thread') {
			backToChatList();
		} else if (state.open === 'chat' && state.channels !== null) {
			closeChannels();
		} else if (state.open === 'ai' && aiFull()) {
			toggleAiFull();
		} else if (state.open === 'ai' && state.ai.view === 'recent') {
			toggleAiRecent();
		} else {
			close();
		}
		return;
	}
	// Šipky vybírají výsledek hledání, Enter ho otevře; kurzor zůstává v poli
	const plainKey = !e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.isComposing;
	if (plainKey && state.picker && e.target.matches('[data-quickbar-picker-query]')) {
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			movePicker(e.key === 'ArrowDown' ? 1 : -1);
		} else if (e.key === 'Enter') {
			e.preventDefault();
			const picker = state.picker;
			// Výsledky dopsaného textu ještě nedorazily - Enter počká na ně
			if (picker.query.trim().length >= picker.minLength && picker.results === null) {
				picker.enterPending = true;
				return;
			}
			const item = pickerItems()[picker.active];
			if (item) {
				closePicker(item);
			}
		}
		return;
	}
	// Vybíraná zpráva jde přepnout i z klávesnice
	if (plainKey && (e.key === ' ' || e.key === 'Enter') && e.target.matches?.('[data-quickbar-message]')) {
		e.preventDefault();
		toggleMessageSelected(e.target.dataset.quickbarMessage);
		return;
	}
	// Šipky v nabídce plusu
	if (plainKey && state.chatMenu !== null && (e.key === 'ArrowDown' || e.key === 'ArrowUp') && e.target.closest?.('[data-quickbar-chat-menu]')) {
		e.preventDefault();
		const items = [...document.querySelectorAll('[data-quickbar-chat-menu-item]')];
		const index = items.indexOf(e.target.closest('[data-quickbar-chat-menu-item]'));
		items[(index + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus();
		return;
	}
	if (plainKey && state.open === 'search' && e.target.matches('[data-quickbar-search]')) {
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			moveSelection(e.key === 'ArrowDown' ? 1 : -1);
			return;
		}
		if (e.key === 'Enter') {
			e.preventDefault();
			// Výsledky dopsaného textu ještě nedorazily (debounce, síť) - otevře se první z nich,
			// až přijdou, ne výsledek předchozího hledání
			if (state.searchShown === state.searchRequest) {
				openSelectedResult();
			} else {
				state.enterRequest = state.searchRequest;
			}
			return;
		}
	}
	const typing = e.target.closest('input, textarea, select, [contenteditable]');
	if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !typing)) {
		if (searchInput()) {
			e.preventDefault();
			open('search');
		}
	}
});

// Lišta je mimo snippet body, ajax ji nepřekreslí - nová lišta přijde jen s jinou stránkou
// bez ajaxu (nebo kdyby ji projekt měl ve vlastním snippetu); pak stav panelů srovnat s DOM
function init() {
	const $bar = bar();
	if ($bar === currentBar) {
		return;
	}
	const firstBar = currentBar === null;
	currentBar = $bar;
	if (!$bar) {
		return;
	}
	// Okno otevřené na předchozí liště zůstane otevřené i na nové
	const previous = openPanelState();
	if (/Mac|iPhone|iPad/.test(navigator.platform)) {
		$bar.querySelector('[data-quickbar-shortcut]')?.replaceChildren('⌘ K');
	}
	state.open = null;
	state.query = '';
	// Nová lišta = nový (prázdný) AI panel, widget se připojí při dalším otevření
	destroyAi();
	state.ai.config = null;
	state.ai.loading = null;
	state.ai.waiting.clear();
	observePanels();
	firstBar ? loadCounts() : render();
	if (previous) {
		restoreOpenPanel(previous);
	}
}

// Otevřené okno zůstane otevřené i na další stránce a po obnovení (F5): při odchodu
// ze stránky si stav uložíme do sessionStorage záložky. Hledání se neobnovuje.
function openPanelState() {
	if (state.open === null || state.open === 'search') {
		return null;
	}
	return { open: state.open, conversationId: state.ai.conversationId, full: state.ai.full };
}

function saveOpenPanel() {
	try {
		const saved = openPanelState();
		saved ? sessionStorage.setItem(OPEN_KEY, JSON.stringify(saved)) : sessionStorage.removeItem(OPEN_KEY);
	} catch {
		// Bez sessionStorage se okno na další stránce jen neotevře
	}
}

function loadOpenPanel() {
	try {
		const saved = JSON.parse(sessionStorage.getItem(OPEN_KEY) || 'null');
		sessionStorage.removeItem(OPEN_KEY);
		return saved;
	} catch {
		return null;
	}
}

function restoreOpenPanel(saved) {
	if (!saved || !panel(saved.open)) {
		return;
	}
	if (saved.open === 'ai') {
		state.ai.restore = saved;
	}
	open(saved.open, false);
}

window.addEventListener('pagehide', saveOpenPanel);

document.addEventListener('DOMContentLoaded', () => {
	const saved = loadOpenPanel();
	init();
	restoreOpenPanel(saved);
});

onContentLoaded(init);
