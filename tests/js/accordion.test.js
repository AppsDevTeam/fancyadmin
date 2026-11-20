// Accordion sekcí (assets/js/accordion.js): jedna otevřená sekce, sekce s chybou, ?section=, zapamatování
// v sessionStorage a odznak napojený na zaškrtávátko. Bez window.bootstrap modul jen přepíná třídy.
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let naja;
let stopObserving;

const nextTick = () => new Promise((resolve) => setTimeout(resolve));

function section(id, { open = false, body = '', badge = null } = {}) {
	const badgeAttrs = badge ? ` data-accordion-badge-source="isActive" data-accordion-badge-on="Aktivní" data-accordion-badge-off="Neaktivní"` : '';
	const badgeHtml = badge ? `<span class="accordion-section-badge badge text-bg-success" data-accordion-badge>${badge}</span>` : '';
	return `<section class="accordion-section${open ? ' is-open' : ''}" data-accordion-section="${id}"${badgeAttrs}>
		<button type="button" class="accordion-section-header${open ? '' : ' collapsed'}" data-bs-toggle="collapse" data-bs-target="#accordion-section-${id}" aria-expanded="${open}">${id}${badgeHtml}</button>
		<div id="accordion-section-${id}" class="collapse${open ? ' show' : ''}"><div class="accordion-section-body">${body}</div></div>
	</section>`;
}

async function render(html, { single = true, search = '' } = {}) {
	window.history.replaceState(null, '', '/settings/emails' + search);
	const wrap = document.createElement('div');
	wrap.className = 'accordion-sections';
	if (single) {
		wrap.setAttribute('data-single', '');
	}
	wrap.innerHTML = html;
	document.body.append(wrap);
	await nextTick();
	return wrap;
}

const openIds = (wrap) => [...wrap.querySelectorAll('.accordion-section > .collapse.show')].map((el) => el.closest('.accordion-section').dataset.accordionSection);

beforeAll(async () => {
	// jsdom nemá scrollIntoView, ?section= na něj sekci posouvá
	Element.prototype.scrollIntoView = vi.fn();
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	({ stopObserving } = await import('../../assets/js/accordion.js'));
});

beforeEach(() => {
	document.body.innerHTML = '';
	window.sessionStorage.clear();
});

afterAll(() => {
	stopObserving();
	document.body.innerHTML = '';
});

describe('otevřená sekce', () => {
	it('s data-single dostanou sekce data-bs-parent obalu', async () => {
		const wrap = await render(section('a', { open: true }) + section('b'));

		expect(wrap.id).not.toBe('');
		expect(wrap.querySelector('#accordion-section-b').getAttribute('data-bs-parent')).toBe('#' + wrap.id);
	});

	it('bez data-single se data-bs-parent nenastaví', async () => {
		const wrap = await render(section('a') + section('b'), { single: false });

		expect(wrap.querySelector('#accordion-section-a').hasAttribute('data-bs-parent')).toBe(false);
	});

	it('otevře sekci s chybou validace a zavře tu, kterou otevřel server', async () => {
		const wrap = await render(section('a', { open: true }) + section('b', { body: '<input class="form-control is-invalid">' }));

		expect(openIds(wrap)).toEqual(['b']);
		expect(wrap.querySelector('[data-accordion-section="b"]').classList.contains('is-open')).toBe(true);
		expect(wrap.querySelector('[data-accordion-section="a"] .accordion-section-header').classList.contains('collapsed')).toBe(true);
	});

	it('?section= otevře požadovanou sekci', async () => {
		const wrap = await render(section('a', { open: true }) + section('b') + section('c'), { search: '?section=c' });

		expect(openIds(wrap)).toEqual(['c']);
	});

	it('chyba má přednost před ?section=', async () => {
		const wrap = await render(section('a', { body: '<div id="snippet-frm-x-errors">Povinné</div>' }) + section('b'), { search: '?section=b' });

		expect(openIds(wrap)).toEqual(['a']);
	});

	it('otevřená sekce se zapamatuje a po návratu na stránku otevře', async () => {
		const first = await render(section('a', { open: true }) + section('b'));
		const b = first.querySelector('#accordion-section-b');
		b.classList.add('show');
		b.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));

		expect(first.id).toBe('accordion-sections-0');
		expect(window.sessionStorage.getItem('fancyadmin-accordion:/settings/emails:accordion-sections-0')).toBe('b');

		document.body.innerHTML = '';
		const again = await render(section('a', { open: true }) + section('b'));

		expect(openIds(again)).toEqual(['b']);
	});

	it('zavřená sekce se zapomene', async () => {
		const wrap = await render(section('a', { open: true }));
		const key = 'fancyadmin-accordion:/settings/emails:' + wrap.id;
		window.sessionStorage.setItem(key, 'a');
		const a = wrap.querySelector('#accordion-section-a');
		a.classList.remove('show');
		a.dispatchEvent(new Event('hidden.bs.collapse', { bubbles: true }));

		expect(window.sessionStorage.getItem(key)).toBeNull();
		expect(wrap.querySelector('[data-accordion-section="a"]').classList.contains('is-open')).toBe(false);
	});

	it('chyba z AJAXového odeslání otevře sbalenou sekci', async () => {
		const wrap = await render(section('a', { open: true }) + section('b', { body: '<div id="snippet-frm-b-errors"></div>' }));
		expect(openIds(wrap)).toEqual(['a']);

		const errors = wrap.querySelector('#snippet-frm-b-errors');
		errors.textContent = 'Vyplňte předmět';
		naja.snippetHandler.dispatchEvent(new CustomEvent('afterUpdate', { detail: { snippet: errors } }));

		expect(openIds(wrap)).toEqual(['b']);
	});
});

describe('odznak podle pole', () => {
	it('přepne text a barvu hned při změně zaškrtávátka', async () => {
		const wrap = await render(section('cash', { badge: 'Aktivní', body: '<input type="checkbox" name="isActive" checked>' }));
		const badge = wrap.querySelector('[data-accordion-badge]');
		const checkbox = wrap.querySelector('[name=isActive]');

		checkbox.checked = false;
		checkbox.dispatchEvent(new Event('change', { bubbles: true }));
		expect(badge.textContent).toBe('Neaktivní');
		expect(badge.classList.contains('text-bg-secondary')).toBe(true);
		expect(badge.classList.contains('text-bg-success')).toBe(false);

		checkbox.checked = true;
		checkbox.dispatchEvent(new Event('change', { bubbles: true }));
		expect(badge.textContent).toBe('Aktivní');
		expect(badge.classList.contains('text-bg-success')).toBe(true);
	});

	it('jiné pole odznak nemění', async () => {
		const wrap = await render(section('cash', { badge: 'Aktivní', body: '<input type="checkbox" name="isActive" checked><input type="checkbox" name="other">' }));
		const other = wrap.querySelector('[name=other]');

		other.dispatchEvent(new Event('change', { bubbles: true }));

		expect(wrap.querySelector('[data-accordion-badge]').textContent).toBe('Aktivní');
	});
});
