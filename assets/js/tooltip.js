// Tooltip pro [data-bs-toggle="tooltip"] (docs/tooltip.md) - Bootstrap jen kreslí, kdy ho ukázat řeší tenhle modul
import { Tooltip } from 'bootstrap';
import { onBeforeSnippetUpdate } from './naja';

const SELECTOR = '[data-bs-toggle="tooltip"]';
const SHOW_DELAY = 400;
// Přejetí na sousední spouštěč do této doby ukáže tooltip hned
const WARM_WINDOW = 300;
const OPPOSITE = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };

let current = null;
let pending = null;
let hiddenAt = 0;

function instance(el) {
	const placement = el.dataset.bsPlacement || 'top';
	return Tooltip.getOrCreateInstance(el, {
		trigger: 'manual',
		// Bez fade třídy Bootstrapu: skrytí je okamžité a show() během odjezdu nesmaže novou bublinu
		animation: false,
		html: true,
		fallbackPlacements: [OPPOSITE[placement] ?? 'bottom'],
		allowList: { ...Tooltip.Default.allowList, kbd: [] },
	});
}

function cancel() {
	clearTimeout(pending?.timer);
	pending = null;
}

function show(el) {
	cancel();
	if (current === el || !el.isConnected) {
		return;
	}
	hide();
	instance(el).show();
	current = el;
}

function hide() {
	cancel();
	if (!current) {
		return;
	}
	Tooltip.getInstance(current)?.hide();
	current = null;
	hiddenAt = Date.now();
}

function schedule(el) {
	if (current === el || pending?.el === el) {
		return;
	}
	// Instance hned, aby Bootstrap stihl odebrat title dřív, než ukáže nativní bublinu prohlížeč
	instance(el);
	if (current || Date.now() - hiddenAt < WARM_WINDOW) {
		show(el);
		return;
	}
	cancel();
	pending = { el, timer: setTimeout(() => show(el), SHOW_DELAY) };
}

function leave(el) {
	if (pending?.el === el) {
		cancel();
	}
	if (current === el) {
		hide();
	}
}

// Na dotyku se tooltip neukazuje, proto do něj nepatří nic nepostradatelného
document.addEventListener('pointerover', (e) => {
	const el = e.pointerType !== 'touch' && e.target.closest?.(SELECTOR);
	if (el) {
		schedule(el);
	}
});

document.addEventListener('pointerout', (e) => {
	const el = e.target.closest?.(SELECTOR);
	if (el && !el.contains(e.relatedTarget) && !el.matches(':focus-visible')) {
		leave(el);
	}
});

document.addEventListener('focusin', (e) => {
	const el = e.target.closest?.(SELECTOR);
	if (el?.matches(':focus-visible')) {
		schedule(el);
	}
});

document.addEventListener('focusout', (e) => {
	const el = e.target.closest?.(SELECTOR);
	if (el) {
		leave(el);
	}
});

document.addEventListener('pointerdown', hide, true);

// Esc zavře jen tooltip, otevřený modal nebo panel zůstane
document.addEventListener('keydown', (e) => {
	if (e.key === 'Escape' && current) {
		e.stopPropagation();
		hide();
	}
}, true);

onBeforeSnippetUpdate(($snippet) => {
	const snippet = $snippet[0];
	if (current && snippet.contains(current)) {
		hide();
	}
	for (const el of snippet.querySelectorAll(SELECTOR)) {
		Tooltip.getInstance(el)?.dispose();
	}
});
