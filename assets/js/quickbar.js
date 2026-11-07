// Rychlá lišta dole uprostřed (@quickbar.latte) - hledání a panely Notifikace, Úkoly, Chat.
// Data dodává projekt přes setQuickbarSource(); bez něj jsou panely prázdné.
//
// Zdroj dat (všechny metody vrací Promise, chybějící metoda = prázdný výsledek):
//   search(query) → [{ label, items: [{ label, meta?, initials?, icon?, tone?, badge?: { label, class }, href?, panel? }] }]
//   notifications: { list() → [{ id, icon, tone?, title, text, time, unread }], read(id), readAll() }
//   todos: { list() → [{ id, text, due?, done }], add(text) → todo, setDone(id, done) }
//   chats: { list() → [{ id, name, unread, preview?, time?, kind?: 'colleague'|'group', initials?, contact? }] (od nejnovější zprávy;
//            contact = kolega bez konverzace, v seznamu jen při hledání),
//            messages(chatId) → [{ mine, text, time }], send(chatId, text) → message, read(chatId) }
// Otevřený chat obnovuje projekt voláním refreshQuickbarChat() (polling, push).
//
// Na mobilu je v liště i Menu (@quickbarMenu.latte) místo bočního menu a AI se neroztahuje na celou plochu.
//
// AI asistent zdroj nepotřebuje - panel hostí widget adt-ai-chat (assets/js/aiChat.js), konfiguraci dá AiPresenter.
// Konverzace můžou nést volitelně `preview` (náhled poslední zprávy) a `unread` (nepřečtená odpověď).

import * as speech from './quickbarSpeech.js';

const SEARCH_DEBOUNCE_MS = 200;
const AI_UNREAD_KEY = 'fancyadmin-quickbar-ai-unread';
// Posuvné části panelů - podle nich se ukazují linky pod hlavičkou a nad patičkou
const PANEL_SCROLLERS = '.quickbar-panel-body, .quickbar-thread, .quickbar-ai-recent, .ai-chat__messages';

// Zlom sm Bootstrapu, stejně jako media-breakpoint-down(sm) v _quickbar.scss
const mobileQuery = window.matchMedia('(max-width: 575.98px)');

let source = {};

const state = {
	open: null,
	query: '',
	searchRequest: 0,
	notifications: [],
	todos: [],
	chats: [],
	chatView: 'list',
	chatQuery: '',
	activeChat: null,
	messages: [],
	ai: { config: null, historyUrl: null, view: 'chat', full: false, query: '', conversations: [], conversationId: null, element: null, waiting: new Set(), unread: loadAiUnread() },
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

// Hledání bez ohledu na velikost písmen a diakritiku
function normalize(value) {
	return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function initials(name) {
	return String(name ?? '').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
}

// === Otevírání a zavírání ===

function open(name) {
	if (!panel(name)) {
		return;
	}
	state.open = name;
	state.ai.full = false;
	// Chat se otevírá seznamem konverzací
	state.chatView = 'list';
	render();

	if (name === 'search') {
		searchInput()?.focus();
		search(state.query);
	} else {
		searchInput()?.blur();
		panel(name).querySelector('.quickbar-panel-form input')?.focus();
		if (name === 'chat') {
			panel('chat').querySelector('[data-quickbar-chat-query]')?.focus();
			loadChats();
		}
		if (name === 'ai') {
			openAi();
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

	$bar.classList.toggle('quickbar--open-search', state.open === 'search');
	$bar.classList.toggle('quickbar--wide', state.open === 'search' || state.open === 'ai');
	document.querySelector('.quickbar-overlay').hidden = state.open === null;
	setColumnOpen(state.open !== null);
	document.querySelectorAll('[data-quickbar-panel]').forEach((el) => {
		el.hidden = el.dataset.quickbarPanel !== state.open;
	});
	panel('chat')?.classList.toggle('quickbar-panel--chat-thread', state.chatView === 'thread');
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
		chat: state.chats.reduce((sum, c) => sum + (c.unread || 0), 0),
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
	]).then(render);
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
		return;
	}

	let index = 0;
	$results.innerHTML = groups.map((group) => `
		<div class="quickbar-group-title">${esc(group.label)}</div>
		${group.items.map((item) => resultItem(item, index++)).join('')}
	`).join('');
}

function resultItem(item, index) {
	const tag = item.href ? 'a' : 'button';
	const attrs = item.href ? `href="${esc(item.href)}"` : 'type="button"';
	const leading = item.initials
		? `<span class="quickbar-avatar">${esc(item.initials)}</span>`
		: `<span class="quickbar-item-icon quickbar-item-icon--tone-${esc(item.tone || 'muted')}"><i class="${esc(item.icon)}"></i></span>`;

	return `<${tag} class="quickbar-item" ${attrs} data-quickbar-result="${index}">
		${leading}
		<span class="quickbar-item-text">
			<span class="quickbar-item-label ${item.meta ? 'quickbar-item-label--strong' : ''}">${esc(item.label)}</span>
			${item.meta ? `<span class="quickbar-item-meta">${esc(item.meta)}</span>` : ''}
		</span>
		${item.badge ? `<span class="badge ${esc(item.badge.class)}">${esc(item.badge.label)}</span>` : ''}
	</${tag}>`;
}

// === Notifikace ===

function renderNotifications() {
	const $list = document.querySelector('[data-quickbar-list="notifications"]');
	if (!$list) {
		return;
	}
	setHtml($list, state.notifications.length
		? state.notifications.map((n) => `
			<button type="button" class="quickbar-item quickbar-notification" data-quickbar-notification="${esc(n.id)}">
				<span class="quickbar-item-icon quickbar-item-icon--tone-${esc(n.tone || 'muted')}"><i class="${esc(n.icon)}"></i></span>
				<span class="quickbar-item-text">
					<span class="quickbar-item-label quickbar-item-label--strong">${esc(n.title)}</span>
					<span class="quickbar-notification-text">${esc(n.text)}</span>
					<span class="quickbar-notification-time">${esc(n.time)}</span>
				</span>
				${n.unread ? '<span class="quickbar-unread-dot"></span>' : ''}
			</button>`).join('')
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

function renderChat() {
	const $list = document.querySelector('[data-quickbar-chats]');
	const $thread = document.querySelector('[data-quickbar-thread]');
	if (!$list || !$thread) {
		return;
	}
	renderChatList($list);
	renderChatCurrent();

	setHtml($thread, state.messages.map((m) => `
		<div class="quickbar-message ${m.mine ? 'quickbar-message--mine' : ''}">
			<div class="quickbar-bubble">${esc(m.text)}</div>
			<div class="quickbar-message-time">${esc(m.time)}</div>
		</div>`).join(''));
	// Na konec jen při nové zprávě nebo jiné konverzaci - polling by jinak vracel čtenáře dolů
	if (renderedThread.chat !== state.activeChat || renderedThread.count !== state.messages.length) {
		$thread.scrollTop = $thread.scrollHeight;
		renderedThread = { chat: state.activeChat, count: state.messages.length };
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
					<span class="quickbar-chat-name">${esc(c.name)}</span>
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

function chatAvatar(chat, small = false) {
	const classes = ['quickbar-chat-avatar', chat.kind === 'group' ? 'quickbar-chat-avatar--group' : '', small ? 'quickbar-chat-avatar--sm' : ''];
	return `<span class="${classes.join(' ')}">${esc(chat.initials || initials(chat.name))}</span>`;
}

function renderChatCurrent() {
	const $current = document.querySelector('[data-quickbar-chat-current]');
	const chat = state.chats.find((c) => c.id === state.activeChat);
	if (!$current || !chat) {
		return;
	}
	setHtml($current, `
		<button type="button" class="btn btn-icon btn-sm btn-ghost" title="${esc($current.dataset.back)}" data-quickbar-chat-back><i class="fa-regular fa-arrow-left"></i></button>
		${chatAvatar(chat, true)}
		<span class="quickbar-chat-current-text">
			<span class="quickbar-chat-current-name">${esc(chat.name)}</span>
			<span class="quickbar-chat-current-sub">${esc(chat.kind === 'group' ? $current.dataset.group : $current.dataset.colleague)}</span>
		</span>`);
}

function loadChats() {
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

function loadMessages(id) {
	return call('chats.messages', id).then((messages) => {
		if (state.activeChat === id) {
			state.messages = messages || [];
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
	loadMessages(id);
}

function backToChatList() {
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
	state.chatView === 'thread' && state.activeChat !== null ? loadMessages(state.activeChat) : loadChats();
}

function sendMessage(text) {
	const chatId = state.activeChat;
	if (chatId === null) {
		return;
	}
	call('chats.send', chatId, text).then((message) => {
		if (state.activeChat === chatId) {
			state.messages.push(message || { mine: true, text, time: '' });
			render();
		}
	});
}

// === AI asistent ===

const aiRoot = () => document.querySelector('[data-quickbar-ai]');
// Instanci widgetu na element věší aiChat.js; bez něj (projekt AI nemá) zůstane panel prázdný
const aiWidget = () => state.ai.element?.adtAiChat ?? null;

function aiRequest(url) {
	return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
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

function openAi() {
	const $root = aiRoot();
	if (!$root) {
		return;
	}
	state.ai.view = 'chat';

	if (state.ai.config) {
		render();
		focusAi();
		return;
	}

	// Po otevření vždy poslední konverzace, bez ní prázdný stav
	aiRequest($root.dataset.quickbarAi)
		.then((payload) => {
			state.ai.config = payload.chat;
			state.ai.historyUrl = payload.historyUrl;
			return loadAiConversations();
		})
		.then(() => {
			mountAi(state.ai.conversations[0]?.id ?? null);
			render();
			focusAi();
		})
		.catch(() => {
			const $chat = document.querySelector('[data-quickbar-ai-chat]');
			$chat.innerHTML = `<div class="quickbar-empty">${esc($chat.dataset.unavailable)}</div>`;
		});
}

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
	// Na celé ploše je nadpisem název konverzace
	const conversation = state.ai.conversations.find((c) => String(c.id) === String(state.ai.conversationId));
	const $rows = $panel.querySelector('[data-quickbar-ai-list]');
	$title.textContent = aiFull() ? (conversation?.title || $rows.dataset.untitled) : $title.dataset.quickbarTitle;

	renderAiSpeak();
	const $full = $panel.querySelector('[data-quickbar-ai-full]');
	$full.title = state.ai.full ? $full.dataset.shrink : $full.dataset.expand;
	$full.querySelector('i').className = state.ai.full ? 'fa-regular fa-down-left-and-up-right-to-center' : 'fa-regular fa-up-right-and-down-left-from-center';
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
	$button.querySelector('i').className = speaking ? 'fa-regular fa-stop'
		: (missing ? 'fa-regular fa-volume-slash' : (enabled ? 'fa-regular fa-volume-high' : 'fa-regular fa-volume-xmark'));
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
	if (!e.target.closest?.('[data-quickbar-ai-chat]') || !$.nette?.ajax) {
		return;
	}
	if (e.detail.auto && state.open !== 'ai') {
		return;
	}
	e.preventDefault();
	// Odkaz a klik jako původce požadavku (nette.ajax bez nich nevyplní settings.nette) -
	// podle odkazu history.ajax.js zapíše URL do historie
	const link = document.createElement('a');
	link.href = e.detail.url;
	$.nette.ajax({ url: link.href }, link, $.Event('click'));
});

// Pevná zkratka diktování: aplikace ADTalk (adt/adtalk)
// pošle Ctrl+Alt+Shift+A a přepis pak napíše do pole - funguje v každém projektu s AI v liště
const DICTATION_CHORD = { code: 'KeyA', ctrlKey: true, altKey: true, shiftKey: true, metaKey: false };

document.addEventListener('keydown', (e) => {
	if (e.repeat || !aiRoot() || Object.entries(DICTATION_CHORD).some(([key, value]) => e[key] !== value)) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	// Nový povel - předčítání předchozí odpovědi by se pletlo do nahrávání
	stopAiSpeech();
	openQuickbarAi();
}, true);

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
	}
}, true);

window.addEventListener('resize', scheduleMeasure);

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
	// Odkaz menu si naviguje sám, okno se jen zavře
	if (target.closest('[data-quickbar-menu] a[href]')) {
		close();
		return;
	}
	if (target.closest('[data-quickbar-chat-back]')) {
		backToChatList();
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
		return;
	}
	const chatRow = target.closest('[data-quickbar-chat]');
	if (chatRow) {
		const chat = state.chats.find((c) => String(c.id) === chatRow.dataset.quickbarChat);
		openChat(chat ? chat.id : null);
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

document.addEventListener('change', (e) => {
	const checkbox = e.target.closest('[data-quickbar-todo]');
	if (checkbox) {
		setTodoDone(checkbox.dataset.quickbarTodo, checkbox.checked);
	}
});

document.addEventListener('input', (e) => {
	if (e.target.matches('[data-quickbar-chat-query]')) {
		state.chatQuery = e.target.value;
		renderChat();
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
	const form = e.target.closest('[data-quickbar-form]');
	if (!form) {
		return;
	}
	// Formuláře lišty nikam neodesílají - jinak by je chytil nette.ajax
	e.preventDefault();
	e.stopImmediatePropagation();
	const input = form.querySelector('input');
	const text = input.value.trim();
	if (!text) {
		return;
	}
	input.value = '';
	form.dataset.quickbarForm === 'todo' ? addTodo(text) : sendMessage(text);
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
		if (state.open === 'chat' && state.chatView === 'thread') {
			backToChatList();
		} else if (state.open === 'ai' && aiFull()) {
			toggleAiFull();
		} else if (state.open === 'ai' && state.ai.view === 'recent') {
			toggleAiRecent();
		} else {
			close();
		}
		return;
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
	if (/Mac|iPhone|iPad/.test(navigator.platform)) {
		$bar.querySelector('[data-quickbar-shortcut]')?.replaceChildren('⌘ K');
	}
	state.open = null;
	state.query = '';
	// Nová lišta = nový (prázdný) AI panel, widget se připojí při dalším otevření
	destroyAi();
	state.ai.config = null;
	state.ai.waiting.clear();
	observePanels();
	firstBar ? loadCounts() : render();
}

document.addEventListener('DOMContentLoaded', init);

if ($.nette && $.nette.ext('live')) {
	$.nette.ext('live').after(init);
}
