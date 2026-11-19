// Sekce accordionu (docs/accordion.md, šablona src/UI/Components/Accordion/accordion.latte).
// Otevírá a zavírá Bootstrap collapse (data API), modul přidává: s data-single nejvýš jednu otevřenou
// sekci, otevření sekce s chybou validace, zapamatování otevřené sekce v sessionStorage podle cesty
// (po Uložit a redirect('this') zůstane otevřená), ?section=<id> v adrese a odznak napojený na
// zaškrtávátko sekce (data-accordion-badge-source).
import { observe } from 'adt-js-components';
import { onContentLoaded } from './naja';

const SECTION = '.accordion-section';
const ERROR = '.is-invalid, .alert-danger, [id^="snippet-"][id$="-errors"]:not(:empty)';

const storageKey = (wrap) => 'fancyadmin-accordion:' + window.location.pathname + ':' + (wrap.id || '');

const sections = (wrap) => [...wrap.querySelectorAll(':scope > ' + SECTION)];
const body = (section) => section.querySelector(':scope > .collapse');
const header = (section) => section.querySelector(':scope > .accordion-section-header');

function markOpen(section, open) {
	section.classList.toggle('is-open', open);
	header(section)?.classList.toggle('collapsed', !open);
	header(section)?.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function setOpen(section, open) {
	const collapse = body(section);
	if (!collapse) {
		return;
	}
	const Collapse = window.bootstrap?.Collapse;
	if (Collapse) {
		const instance = Collapse.getOrCreateInstance(collapse, { toggle: false });
		open ? instance.show() : instance.hide();
	} else {
		collapse.classList.toggle('show', open);
	}
	markOpen(section, open);
}

export function openSection(section) {
	const wrap = section.closest('.accordion-sections');
	if (wrap?.hasAttribute('data-single')) {
		sections(wrap).filter((other) => other !== section && body(other)?.classList.contains('show')).forEach((other) => setOpen(other, false));
	}
	setOpen(section, true);
}

function sectionById(wrap, id) {
	return sections(wrap).find((section) => section.dataset.accordionSection === id) ?? null;
}

function updateBadge(section) {
	const name = section.dataset.accordionBadgeSource;
	const badge = section.querySelector(':scope > .accordion-section-header [data-accordion-badge]');
	const field = name && [...section.querySelectorAll('input, select')].find((input) => input.name === name);
	if (!badge || !field) {
		return;
	}
	const on = field.type === 'checkbox' ? field.checked : !!field.value;
	badge.textContent = on ? section.dataset.accordionBadgeOn : section.dataset.accordionBadgeOff;
	badge.classList.toggle('text-bg-success', on);
	badge.classList.toggle('text-bg-secondary', !on);
}

function init(wrap) {
	// Stálé id podle pořadí na stránce - nese data-bs-parent i klíč zapamatování
	if (!wrap.id) {
		wrap.id = 'accordion-sections-' + [...document.querySelectorAll('.accordion-sections')].indexOf(wrap);
	}
	if (wrap.hasAttribute('data-single')) {
		sections(wrap).forEach((section) => body(section)?.setAttribute('data-bs-parent', '#' + wrap.id));
	}
	sections(wrap).forEach(updateBadge);

	// Přednost: sekce s chybou, ?section=, zapamatovaná, jinak co otevřel server
	const withError = sections(wrap).find((section) => section.querySelector(ERROR));
	const requested = sectionById(wrap, new URLSearchParams(window.location.search).get('section'));
	let remembered = null;
	try {
		remembered = sectionById(wrap, window.sessionStorage.getItem(storageKey(wrap)));
	} catch {
		// sessionStorage nemusí být dostupný (soukromé okno, zakázané úložiště)
	}
	const target = withError ?? requested ?? remembered;
	if (target) {
		openSection(target);
		if (target === requested && !withError) {
			target.scrollIntoView({ block: 'start' });
		}
	}
}

function remember(section, open) {
	const wrap = section.closest('.accordion-sections');
	if (!wrap) {
		return;
	}
	try {
		if (open) {
			window.sessionStorage.setItem(storageKey(wrap), section.dataset.accordionSection);
		} else if (window.sessionStorage.getItem(storageKey(wrap)) === section.dataset.accordionSection) {
			window.sessionStorage.removeItem(storageKey(wrap));
		}
	} catch {
		// bez úložiště se jen nic nezapamatuje
	}
}

document.addEventListener('shown.bs.collapse', (e) => {
	const section = e.target.closest(SECTION);
	if (section && body(section) === e.target) {
		markOpen(section, true);
		remember(section, true);
	}
});

document.addEventListener('hidden.bs.collapse', (e) => {
	const section = e.target.closest(SECTION);
	if (section && body(section) === e.target) {
		markOpen(section, false);
		remember(section, false);
	}
});

document.addEventListener('change', (e) => {
	const section = e.target.closest?.('[data-accordion-badge-source]');
	if (section && e.target.name === section.dataset.accordionBadgeSource) {
		updateBadge(section);
	}
});

// Jeden formulář přes víc sekcí (jedno Uložit pro všechny): validace v prohlížeči označí pole i ve sbalené
// sekci, kde by chybu nikdo neviděl. Validuje se v obsluze odeslání, proto se kontroluje až po ní.
document.addEventListener('click', (e) => {
	const form = e.target.closest?.('[type="submit"]')?.form;
	if (!form?.querySelector('.accordion-sections')) {
		return;
	}
	setTimeout(() => {
		const field = form.querySelector('.accordion-sections ' + SECTION + ' .is-invalid');
		const section = field?.closest(SECTION);
		if (!section || body(section)?.classList.contains('show')) {
			return;
		}
		if (window.bootstrap?.Collapse) {
			body(section).addEventListener('shown.bs.collapse', () => field.focus(), { once: true });
			openSection(section);
		} else {
			openSection(section);
			field.focus();
		}
	});
});

// Chyby z AJAXového odeslání formuláře přijdou překreslením snippetu `…-errors` uvnitř sekce
onContentLoaded(($snippet) => {
	$snippet.each((_, element) => {
		const section = element.closest?.(SECTION);
		if (section && element.matches?.(ERROR) && !body(section)?.classList.contains('show')) {
			openSection(section);
		}
	});
});

export const stopObserving = observe('.accordion-sections', init);
