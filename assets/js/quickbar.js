// Rychlá lišta dole uprostřed (@quickbar.latte) - hledání a panely Notifikace, Úkoly, Chat.
// Data dodává projekt přes setQuickbarSource(); bez něj jsou panely prázdné.
//
// Zdroj dat (všechny metody vrací Promise, chybějící metoda = prázdný výsledek):
//   search(query) → [{ label, items: [{ label, meta?, initials?, icon?, tone?, badge?: { label, class }, href?, panel? }] }]
//   notifications: { list() → [{ id, icon, tone?, title, text, time, unread }], read(id), readAll() }
//   todos: { list() → [{ id, text, due?, done }], add(text) → todo, setDone(id, done) }
//   chats: { list() → [{ id, name, unread }], messages(chatId) → [{ mine, text, time }], send(chatId, text) → message, read(chatId) }
//
// AI asistent zdroj nepotřebuje - panel hostí widget adt-ai-chat (assets/js/aiChat.js), konfiguraci dá AiPresenter.

const SEARCH_DEBOUNCE_MS = 200;

let source = {};

const state = {
	open: null,
	query: '',
	searchRequest: 0,
	notifications: [],
	todos: [],
	chats: [],
	activeChat: null,
	messages: [],
	ai: { config: null, historyUrl: null, view: 'chat', conversations: [], conversationId: null, element: null },
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

function esc(value) {
	return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

// === Otevírání a zavírání ===

function open(name) {
	if (!panel(name)) {
		return;
	}
	state.open = name;
	render();

	if (name === 'search') {
		searchInput()?.focus();
		search(state.query);
	} else {
		searchInput()?.blur();
		panel(name).querySelector('.quickbar-panel-form input')?.focus();
		if (name === 'chat') {
			openChat(state.activeChat ?? state.chats[0]?.id ?? null);
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
	document.querySelectorAll('[data-quickbar-panel]').forEach((el) => {
		el.hidden = el.dataset.quickbarPanel !== state.open;
	});
	document.querySelectorAll('[data-quickbar-toggle]').forEach((button) => {
		const active = button.dataset.quickbarToggle === state.open;
		button.classList.toggle('btn-primary', active);
		button.classList.toggle('btn-outline-secondary', !active);
		button.setAttribute('aria-expanded', active ? 'true' : 'false');
	});

	const input = searchInput();
	if (input) {
		input.setAttribute('aria-expanded', state.open === 'search' ? 'true' : 'false');
		input.placeholder = state.open === 'search' ? input.dataset.placeholderOpen : input.dataset.placeholderClosed;
	}

	renderCounts();
	renderNotifications();
	renderTodos();
	renderChat();
	renderAi();
}

function renderCounts() {
	const counts = {
		notifications: state.notifications.filter((n) => n.unread).length,
		todo: state.todos.filter((t) => !t.done).length,
		chat: state.chats.reduce((sum, c) => sum + (c.unread || 0), 0),
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
	$list.innerHTML = state.notifications.length
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
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`;
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
	$list.innerHTML = state.todos.length
		? state.todos.map((t) => `
			<label class="quickbar-item quickbar-todo ${t.done ? 'quickbar-todo--done' : ''}">
				<input type="checkbox" class="form-check-input" data-quickbar-todo="${esc(t.id)}" ${t.done ? 'checked' : ''}>
				<span class="quickbar-item-text">
					<span class="quickbar-item-label">${esc(t.text)}</span>
					${t.due ? `<span class="quickbar-item-meta"><i class="fa-regular fa-clock"></i> ${esc(t.due)}</span>` : ''}
				</span>
			</label>`).join('')
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`;
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

function renderChat() {
	const $chips = document.querySelector('[data-quickbar-chats]');
	const $thread = document.querySelector('[data-quickbar-thread]');
	if (!$chips || !$thread) {
		return;
	}
	$chips.innerHTML = state.chats.map((c) => `
		<button type="button" class="quickbar-chip ${c.id === state.activeChat ? 'quickbar-chip--active' : ''}" data-quickbar-chat="${esc(c.id)}">
			<span>${esc(c.name)}</span>
			${c.unread ? `<span class="badge bg-danger">${esc(c.unread)}</span>` : ''}
		</button>`).join('');

	$thread.innerHTML = state.messages.map((m) => `
		<div class="quickbar-message ${m.mine ? 'quickbar-message--mine' : ''}">
			<div class="quickbar-bubble">${esc(m.text)}</div>
			<div class="quickbar-message-time">${esc(m.time)}</div>
		</div>`).join('');
	$thread.scrollTop = $thread.scrollHeight;
}

function openChat(id) {
	if (id === null) {
		return;
	}
	state.activeChat = id;
	const chat = state.chats.find((c) => c.id === id);
	if (chat && chat.unread) {
		chat.unread = 0;
		call('chats.read', id);
	}
	render();
	call('chats.messages', id).then((messages) => {
		if (state.activeChat === id) {
			state.messages = messages || [];
			render();
		}
	});
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

function openAi() {
	const $root = aiRoot();
	if (!$root) {
		return;
	}
	state.ai.view = 'chat';

	if (state.ai.config) {
		renderAi();
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
		renderAi();
	});
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
	// Widget se připojuje až v MutationObserveru aiChat.js
	setTimeout(() => document.querySelector('[data-quickbar-ai-chat] textarea')?.focus(), 50);
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
	renderAi();
	focusAi();
}

function toggleAiRecent() {
	if (!state.ai.config) {
		return;
	}
	state.ai.view = state.ai.view === 'recent' ? 'chat' : 'recent';
	renderAi();
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

function renderAi() {
	const $root = aiRoot();
	if (!$root) {
		return;
	}
	const recent = state.ai.view === 'recent';
	$root.classList.toggle('quickbar-ai--recent', recent);
	document.querySelectorAll('[data-quickbar-ai-recent-toggle].btn').forEach((button) => {
		button.classList.toggle('btn-primary', recent);
		button.classList.toggle('btn-outline-secondary', !recent);
	});

	const $list = $root.querySelector('[data-quickbar-ai-list]');
	const conversations = state.ai.conversations;
	$list.innerHTML = conversations.length
		? conversations.map((c) => {
			const current = String(c.id) === String(state.ai.conversationId);
			return `<button type="button" class="quickbar-ai-conversation ${current ? 'quickbar-ai-conversation--current' : ''}" data-quickbar-ai-conversation="${esc(c.id)}">
				<i class="fa-regular fa-message"></i>
				<span class="quickbar-ai-conversation-title">${esc(c.title || $list.dataset.untitled)}</span>
				${current ? `<span class="badge bg-secondary">${esc($list.dataset.current)}</span>` : ''}
				<span class="quickbar-ai-conversation-time">${esc(formatAiTime(c.updated))}</span>
			</button>`;
		}).join('')
		: `<div class="quickbar-empty">${esc($list.dataset.empty)}</div>`;

	const $all = $root.querySelector('[data-quickbar-ai-all]');
	$all.hidden = !conversations.length || !state.ai.historyUrl;
	if (state.ai.historyUrl) {
		$all.href = state.ai.historyUrl;
	}
}

// Widget hlásí založení konverzace i doručenou odpověď
document.addEventListener('ai-chat:conversation', (e) => {
	if (e.target.closest?.('[data-quickbar-ai-chat]') && e.detail?.conversation?.id) {
		state.ai.conversationId = e.detail.conversation.id;
	}
});

// === Události (delegace na document - lišta je ve snippetu body a překresluje se) ===

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
	if (target.closest('[data-quickbar-ai-new]')) {
		openAiConversation(null);
		return;
	}
	if (target.closest('[data-quickbar-ai-recent-toggle]')) {
		toggleAiRecent();
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
	const chip = target.closest('[data-quickbar-chat]');
	if (chip) {
		const chat = state.chats.find((c) => String(c.id) === chip.dataset.quickbarChat);
		openChat(chat ? chat.id : null);
		panel('chat')?.querySelector('.quickbar-panel-form input')?.focus();
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
	if (e.key === 'Escape' && state.open !== null) {
		e.preventDefault();
		close();
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

// Překreslený snippet body přinese novou (zavřenou) lištu - stav panelů srovnat s DOM
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
	firstBar ? loadCounts() : render();
}

document.addEventListener('DOMContentLoaded', init);

if ($.nette && $.nette.ext('live')) {
	$.nette.ext('live').after(init);
}
