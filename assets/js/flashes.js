import $ from 'jquery';

// Rozměry a časy balíčku zpráv (docs/flashes.md)
const GAP = 8;
const PEEK = 10;
const SCALE_STEP = 0.05;
const MAX_PEEKING = 3;
const ENTER_OFFSET = 16;
const ENTER_DELAY = 30;
const LEAVE_DURATION = 300;
const TICK = 100;

const ICONS = {
	success: 'fa-circle-check',
	info: 'fa-circle-info',
	warning: 'fa-triangle-exclamation',
	danger: 'fa-circle-exclamation',
};

let toasts = [];
let hovered = false;
let ticker = null;

const getStack = () => document.querySelector('.flash-stack');

/**
 * Hlášku z jednoho textu rozdělí na nadpis (první věta) a text (zbytek). Zprávy s HTML
 * zůstávají celé v nadpisu, dělením by se rozbily značky.
 */
export const splitMessage = (node) => {
	if (node.children.length) {
		return { title: node.innerHTML.trim(), text: '', isHtml: true };
	}
	const message = node.textContent.trim();
	const match = message.match(/^(.+?[.!?])\s+(?=\p{Lu})([\s\S]+)$/u);
	return {
		title: match ? match[1] : message,
		text: match ? match[2] : '',
		isHtml: false,
	};
};

const createElement = (type, parts, stack) => {
	const el = document.createElement('div');
	el.className = 'flash flash-' + type;
	el.setAttribute('role', type === 'warning' || type === 'danger' ? 'alert' : 'status');
	el.innerHTML =
		'<div class="flash-body">' +
			'<div class="flash-row">' +
				'<div class="flash-icon"><i class="fa-regular ' + (ICONS[type] || ICONS.info) + '"></i></div>' +
				'<div class="flash-content"><div class="flash-title"></div></div>' +
				'<button type="button" class="flash-close"><i class="fa-regular fa-xmark"></i></button>' +
			'</div>' +
		'</div>';

	const title = el.querySelector('.flash-title');
	if (parts.isHtml) {
		title.innerHTML = parts.title;
	} else {
		title.textContent = parts.title;
	}
	if (parts.text) {
		const text = document.createElement('div');
		text.className = 'flash-text';
		text.textContent = parts.text;
		title.after(text);
	}

	const close = el.querySelector('.flash-close');
	close.title = stack.dataset.closeLabel || '';
	close.setAttribute('aria-label', close.title);

	// Varování a chybu musí uživatel vzít na vědomí, samy nezmizí
	if (type === 'warning' || type === 'danger') {
		const actions = document.createElement('div');
		actions.className = 'flash-actions';
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'btn btn-xs btn-outline-primary flash-dismiss';
		button.textContent = stack.dataset.actionLabel || '';
		actions.append(button);
		el.querySelector('.flash-body').append(actions);
	}

	return el;
};

/**
 * Rozmístí zprávy: sbalený balíček (vpředu nejnovější, starší vykukují nad ní), při
 * najetí myší rozestoupené pod sebou. Výška kontejneru je plocha pro hover.
 */
const layout = () => {
	const stack = getStack();
	if (!stack) {
		return;
	}
	const list = [...toasts].reverse();
	const live = list.filter(t => !t.leaving);
	const measure = t => t.body.offsetHeight + 2;
	const frontHeight = live.length ? measure(live[0]) : 0;
	let offset = 0;
	let index = 0;

	for (const t of list) {
		const i = t.leaving ? Math.max(index - 1, 0) : index++;
		let y;
		let scale = 1;
		let height = '';
		let contentOpacity = 1;
		let opacity = 1;

		if (hovered) {
			y = offset;
			if (!t.leaving) {
				offset += measure(t) + GAP;
			}
		} else {
			y = i * PEEK;
			scale = 1 - i * SCALE_STEP;
			if (i > 0) {
				height = frontHeight + 'px';
				contentOpacity = 0;
			}
			if (i >= MAX_PEEKING) {
				opacity = 0;
			}
		}
		if (!t.visible || t.leaving) {
			opacity = 0;
		}

		const enter = t.visible ? 0 : ENTER_OFFSET;
		t.el.style.transform = `translateY(${enter - y}px) scale(${t.leaving ? scale * 0.96 : scale})`;
		t.el.style.height = height;
		t.el.style.opacity = opacity;
		t.el.style.zIndex = 100 - i;
		t.el.classList.toggle('flash--behind', !hovered && i > 0);
		t.body.style.opacity = contentOpacity;
	}

	const total = hovered
		? Math.max(offset - GAP, 0)
		: (live.length ? frontHeight + Math.min(live.length - 1, MAX_PEEKING - 1) * PEEK : 0);
	stack.style.height = total + 'px';
};

const dismiss = (toast) => {
	if (toast.leaving) {
		return;
	}
	toast.leaving = true;
	layout();
	setTimeout(() => {
		toast.el.remove();
		toasts = toasts.filter(t => t !== toast);
		if (!toasts.length) {
			// Myš nad prázdným kontejnerem nepošle mouseleave, příští zpráva by začala rozbalená
			hovered = false;
		}
		layout();
	}, LEAVE_DURATION);
};

// Odpočet zpráv s časovačem, při najetí myší na balíček stojí
const tick = () => {
	if (hovered) {
		return;
	}
	for (const t of toasts) {
		if (!t.duration || t.leaving || !t.visible) {
			continue;
		}
		t.remaining = Math.max(0, t.remaining - TICK);
		t.progress.style.width = (t.remaining / t.duration * 100) + '%';
		if (!t.remaining) {
			dismiss(t);
		}
	}
	if (!toasts.some(t => t.duration && !t.leaving)) {
		clearInterval(ticker);
		ticker = null;
	}
};

const bindStack = (stack) => {
	if (stack.dataset.flashBound) {
		return;
	}
	stack.dataset.flashBound = '1';
	stack.addEventListener('mouseenter', () => {
		hovered = true;
		layout();
	});
	stack.addEventListener('mouseleave', () => {
		hovered = false;
		layout();
	});
	stack.addEventListener('click', (e) => {
		const button = e.target.closest('.flash-close, .flash-dismiss');
		const toast = button && toasts.find(t => t.el.contains(button));
		if (toast) {
			dismiss(toast);
		}
	});
};

const addFlash = (type, node, closeDuration) => {
	const stack = getStack();
	if (!stack) {
		return;
	}
	bindStack(stack);

	const parts = splitMessage(node);
	const key = type + '|' + node.innerHTML;
	if (toasts.some(t => !t.leaving && t.key === key)) {
		return;
	}

	const el = createElement(type, parts, stack);
	const duration = parseInt(closeDuration, 10) || 0;
	let progress = null;
	if (duration) {
		progress = document.createElement('div');
		progress.className = 'flash-progress';
		el.append(progress);
	}
	stack.append(el);

	const toast = { el, body: el.querySelector('.flash-body'), progress, key, duration, remaining: duration, visible: false, leaving: false };
	toasts.push(toast);
	layout();
	setTimeout(() => {
		toast.visible = true;
		layout();
	}, ENTER_DELAY);

	if (duration && !ticker) {
		ticker = setInterval(tick, TICK);
	}
};

let serverFlashInterceptor = null;

/**
 * Převzetí zpráv ze serveru před zobrazením - boční panel si po uložení bere úspěšnou
 * zprávu sám (sidePanel.js). Funkce dostane i prázdný seznam, vrací true, když zprávy
 * převzala; nepřevzaté zobrazí sama přes showServerFlash().
 *
 * @param {?function(Array<{type: string, node: Element, closeDuration: ?string}>): boolean} interceptor
 */
export const interceptServerFlashes = (interceptor) => {
	serverFlashInterceptor = interceptor;
};

export const showServerFlash = (item) => addFlash(item.type, item.node, item.closeDuration);

// Zprávy ze serveru (snippet flashes) přesune do balíčku; zdroj se vyprázdní, aby se
// při dalším AJAXu bez překreslení snippetu nezobrazily znovu
const adoptServerFlashes = () => {
	const items = [...document.querySelectorAll('.flash-source .flash-source-item')].map((item) => {
		item.remove();
		return { type: item.dataset.type, node: item, closeDuration: item.dataset.closeDuration };
	});
	if (serverFlashInterceptor && serverFlashInterceptor(items)) {
		return;
	}
	items.forEach(showServerFlash);
};

/**
 * Zobrazí flash zprávu z JS stejně jako zprávy ze serveru. Stejnou zprávu, která už je
 * zobrazená, nepřidává znovu.
 *
 * @param {string} message prostý text, první věta je nadpis
 * @param {string} type success | info | warning | danger
 * @param {?number} closeDuration ms do automatického zavření, null = zůstane do zavření uživatelem
 */
export const showFlash = (message, type = 'danger', closeDuration = null) => {
	if (!message) {
		return;
	}
	const node = document.createElement('div');
	node.textContent = message;
	addFlash(type, node, closeDuration);
};

/**
 * Hláška pro nezdařené uložení pořadí řádků gridu (přetažení myší).
 */
export const showSortableError = () => {
	showFlash(getStack()?.dataset.sortableError);
};

$(document).ready(adoptServerFlashes);

if (window.$ && window.$.nette) {
	$.nette.ext('flashes', {
		success: adoptServerFlashes
	});
}
