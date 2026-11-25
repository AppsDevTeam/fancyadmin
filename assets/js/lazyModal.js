// Modal s obsahem načítaným až při prvním otevření (docs/lazy-modal.md). data-lazy-url je signál
// lazyComponent! (LazyComponentsTrait), který překreslí snippet těla modalu - stránka se tak
// nezdržuje sestavováním formulářů, které uživatel většinou neotevře. Po načtení dostane modal
// data-lazy-state="loaded" a událost lazy-modal:loaded; kód, který formulář v modalu předvyplňuje,
// na ni počká přes whenLazyModalLoaded().

import { ajax, onContentLoaded } from './naja';
import { focusFirstField, keepTabInside, leaveFieldOnEscape } from './formFocus';

export function whenLazyModalLoaded(modal, callback) {
	if (!modal.dataset.lazyUrl || modal.dataset.lazyState === 'loaded') {
		callback();
		return;
	}
	$(modal).one('lazy-modal:loaded', callback);
}

$(document).on('show.bs.modal', '.modal[data-lazy-url]', function () {
	const modal = this;
	if (modal.dataset.lazyState) {
		return;
	}
	modal.dataset.lazyState = 'loading';
	ajax(modal.dataset.lazyUrl, null, { history: false })
		.then(() => {
			modal.dataset.lazyState = 'loaded';
			$(modal).trigger('lazy-modal:loaded');
		})
		.catch(() => {
			// Další otevření to zkusí znovu
			delete modal.dataset.lazyState;
		});
});

// Kurzor do prvního pole; obsah líného modalu se na Tom Select napojí až po vložení
$(document).on('shown.bs.modal', '.modal', function () {
	const modal = this;
	whenLazyModalLoaded(modal, () => requestAnimationFrame(() => focusFirstField(modal)));
});

// Bootstrap vrací jen fokus, který vyšel ven - TAB z posledního prvku by se zastavil v liště prohlížeče
document.addEventListener('keydown', (e) => {
	const modal = e.target instanceof Element && e.target.closest('.modal.show');
	if (modal) keepTabInside(e, modal);
});

// Bootstrap zavírá modal na Esc posluchačem přímo na .modal, Esc z pole proto zastaví už .modal-dialog
const escapeDialogs = new WeakSet();

$(document).on('show.bs.modal', '.modal', function () {
	const modal = this;
	const dialog = modal.querySelector('.modal-dialog');
	if (!dialog || escapeDialogs.has(dialog)) return;
	escapeDialogs.add(dialog);
	dialog.addEventListener('keydown', (e) => {
		if (leaveFieldOnEscape(e, modal)) e.stopPropagation();
	});
});

// Formulář v modalu se uloží a přesměruje, překreslení stránky nahradí otevřený modal novým zavřeným.
// Backdrop a zámek posunu stránky by pak zůstaly viset, protože je už nemá kdo zavřít.
const openModals = new Set();

$(document).on('show.bs.modal', '.modal', function () {
	openModals.add(this);
});

$(document).on('hidden.bs.modal', '.modal', function () {
	openModals.delete(this);
});

onContentLoaded(() => {
	openModals.forEach((modal) => {
		if (!modal.isConnected) {
			openModals.delete(modal);
		}
	});
	if (openModals.size) {
		return;
	}
	document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
	document.body.classList.remove('modal-open');
	document.body.style.removeProperty('overflow');
	document.body.style.removeProperty('padding-right');
});
