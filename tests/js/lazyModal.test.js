// Modal nahrazený překreslením snippetu (formulář v něm se uložil) nesmí nechat backdrop a zámek stránky
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

let naja;

function snippetUpdated(snippet) {
	naja.snippetHandler.dispatchEvent(new CustomEvent('afterUpdate', { detail: { snippet } }));
}

function openModal(modal) {
	$(modal).trigger('show.bs.modal');
	document.body.insertAdjacentHTML('beforeend', '<div class="modal-backdrop fade show"></div>');
	document.body.classList.add('modal-open');
	document.body.style.overflow = 'hidden';
}

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	await import('../../assets/js/lazyModal.js');
});

beforeEach(() => {
	document.body.className = '';
	document.body.removeAttribute('style');
	document.body.innerHTML = '<div id="snippet--modals"><div class="modal" id="edit"></div></div><div id="snippet--content"></div>';
});

describe('úklid po překreslení modalu', () => {
	it('modal nahrazený novým zavřeným nenechá backdrop ani zámek posunu', () => {
		openModal(document.getElementById('edit'));

		const modals = document.getElementById('snippet--modals');
		modals.innerHTML = '<div class="modal" id="edit"></div>';
		snippetUpdated(modals);

		expect(document.querySelector('.modal-backdrop')).toBeNull();
		expect(document.body.classList.contains('modal-open')).toBe(false);
		expect(document.body.style.overflow).toBe('');
	});

	it('otevřený modal, do kterého se načetl formulář, zůstane otevřený', () => {
		const modal = document.getElementById('edit');
		openModal(modal);

		modal.innerHTML = '<form></form>';
		snippetUpdated(modal);

		expect(document.querySelector('.modal-backdrop')).not.toBeNull();
		expect(document.body.classList.contains('modal-open')).toBe(true);

		$(modal).trigger('hidden.bs.modal');
	});

	it('zavřený modal se nepočítá', () => {
		const modal = document.getElementById('edit');
		openModal(modal);
		$(modal).trigger('hidden.bs.modal');
		document.body.insertAdjacentHTML('beforeend', '<div class="modal-backdrop"></div>');

		snippetUpdated(document.getElementById('snippet--content'));

		expect(document.querySelector('.modal-backdrop')).toBeNull();
	});
});
