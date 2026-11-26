// Filtr v hlavičce sloupce (ColumnFiltersTrait, docs/datagrid-column-filters.md). Panel je Bootstrap dropdown,
// pole se odesílají až tlačítkem Použít. Zavření panelu bez něj vrátí pole na hodnoty ze serveru, takže stav
// filtrů se vždy vykreslí ze serveru a v prohlížeči žije jen otevřeno/zavřeno.

import { Dropdown, Modal } from 'bootstrap';
import flatpickr from 'flatpickr';
import naja from 'naja';

import { onContentLoaded } from './naja';

const TOGGLE = '[data-part="toggle"], [data-part="more-filters"], .fa-filters-chip.dropdown > .fa-filters-chip-edit';
const PANEL = '[data-fa-filters-panel]';

// panel, jehož Použít právě odeslal formulář - po překreslení se fokus vrátí na jeho hlavičku
let submittedFrom = null;

// kam vrátit fokus po překreslení gridu jinou akcí než Použít (přepínač, odebraný čip, Zrušit filtry)
let pendingFocus = null;

let calendarLocale = {};

// Jazyk kalendáře období, stejně jako initDateInput: configureColumnFilters({ locale: Czech })
export function configureColumnFilters({ locale } = {}) {
	calendarLocale = locale ?? {};
}

function panelOf(toggle) {
	const menu = Dropdown.getInstance(toggle)?._menu;
	return menu?.matches(PANEL) ? menu : toggle.parentElement.querySelector(':scope > ' + PANEL);
}

function fields(panel) {
	return panel.querySelectorAll('input[name], select[name]');
}

function snapshot(panel) {
	return Array.from(fields(panel), (el) => {
		if (el.type === 'radio' || el.type === 'checkbox') {
			return el.checked;
		}
		if (el.multiple) {
			return Array.from(el.selectedOptions, (option) => option.value);
		}
		return el.value;
	});
}

function restore(panel, values) {
	Array.from(fields(panel)).forEach((el, index) => {
		const value = values[index];
		if (el.type === 'radio' || el.type === 'checkbox') {
			el.checked = value;
		} else if (el.tomselect) {
			el.tomselect.setValue(value, true);
		} else if (el.multiple) {
			Array.from(el.options).forEach((option) => (option.selected = value.includes(option.value)));
		} else {
			el.value = value;
		}
	});
	panel.querySelectorAll('[data-fa-filters-range]').forEach(syncRangeFromHidden);
}

function clear(panel) {
	fields(panel).forEach((el) => {
		if (el.type === 'radio') {
			const defaultValue = el.closest('[data-fa-filters-default-value]')?.dataset.faFiltersDefaultValue;
			el.checked = el.value === (defaultValue ?? '');
		} else if (el.type === 'checkbox') {
			el.checked = false;
		} else if (el.tomselect) {
			el.tomselect.clear(true);
		} else if (el.multiple) {
			Array.from(el.options).forEach((option) => (option.selected = false));
		} else {
			el.value = '';
		}
	});
}

function submit(panel) {
	submittedFrom = panel.id;
	panel.closest('form').requestSubmit(panel.querySelector('[data-part="apply"]'));
}

// Rozsah dat: viditelné pole s flatpickr v režimu range, hodnoty jdou do skrytých polí od/do filtru knihovny
const PRESETS = {
	today: (now) => [now, now],
	yesterday: (now) => [addDays(now, -1), addDays(now, -1)],
	last7: (now) => [addDays(now, -6), now],
	last30: (now) => [addDays(now, -29), now],
	thisMonth: (now) => [new Date(now.getFullYear(), now.getMonth(), 1), now],
	lastMonth: (now) => [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)],
	thisYear: (now) => [new Date(now.getFullYear(), 0, 1), now],
};

function addDays(date, days) {
	const result = new Date(date);
	result.setDate(result.getDate() + days);
	return result;
}

function today() {
	const now = new Date();
	return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function rangeInputs(input) {
	return [document.getElementById(input.dataset.from), document.getElementById(input.dataset.to)];
}

function sameDay(a, b) {
	return a && b && a.getTime() === b.getTime();
}

function markPreset(input, dates) {
	const now = today();
	input.closest('.fa-filters-panel-body').querySelectorAll('[data-fa-filters-preset]').forEach((button) => {
		const [from, to] = PRESETS[button.dataset.faFiltersPreset](now);
		const active = dates.length === 2 && sameDay(from, dates[0]) && sameDay(to, dates[1]);
		button.classList.toggle('active', active);
		button.setAttribute('aria-pressed', active ? 'true' : 'false');
	});
}

function syncRangeFromHidden(input) {
	const [from, to] = rangeInputs(input);
	const format = input.dataset.format || 'Y-m-d';
	const dates = [from.value, to.value].filter(Boolean).map((value) => flatpickr.parseDate(value, format));
	if (input._flatpickr) {
		input._flatpickr.setDate(dates, false);
	}
	input.value = dates.map((date) => flatpickr.formatDate(date, 'j. n. Y')).join(' – ');
	markPreset(input, dates);
}

function initRange(input) {
	if (input._flatpickr) {
		return;
	}
	const [from, to] = rangeInputs(input);
	const format = input.dataset.format || 'Y-m-d';
	flatpickr(input, {
		mode: 'range',
		inline: true,
		appendTo: input.closest('.fa-filters-field'),
		locale: { ...calendarLocale, rangeSeparator: ' – ' },
		dateFormat: 'j. n. Y',
		allowInput: false,
		onChange: (dates) => {
			from.value = dates[0] ? flatpickr.formatDate(dates[0], format) : '';
			to.value = dates[1] ? flatpickr.formatDate(dates[1], format) : (dates[0] ? from.value : '');
			markPreset(input, dates);
		},
	});
	syncRangeFromHidden(input);
}

document.addEventListener('click', (e) => {
	const preset = e.target.closest('[data-fa-filters-preset]');
	if (preset) {
		const input = preset.closest('.fa-filters-panel-body').querySelector('[data-fa-filters-range]');
		initRange(input);
		input._flatpickr.setDate(PRESETS[preset.dataset.faFiltersPreset](today()), true);
		return;
	}

	const clearButton = e.target.closest('[data-fa-filters-clear]');
	if (clearButton) {
		const panel = clearButton.closest(PANEL);
		clear(panel);
		submit(panel);
		return;
	}

	// Čip filtru viditelného sloupce otevře panel v hlavičce; až po kliknutí, jinak by ho Bootstrap hned zavřel
	const chip = e.target.closest('[data-fa-filters-open]');
	if (chip) {
		const toggle = chip.closest('.datagrid').querySelector(`[data-fa-filters-column="${CSS.escape(chip.dataset.faFiltersOpen)}"]`);
		if (toggle) {
			setTimeout(() => Dropdown.getOrCreateInstance(toggle).show());
		}
		return;
	}

	const move = e.target.closest('[data-fa-filters-column-move]');
	if (move) {
		moveColumn(move);
		return;
	}

	const showAll = e.target.closest('[data-fa-filters-columns-all]');
	if (showAll) {
		showAll.closest('.modal').querySelectorAll('[data-fa-filters-column-visible]').forEach((checkbox) => {
			checkbox.checked = true;
			checkbox.closest('[data-fa-filters-column-key]').classList.remove('is-hidden');
		});
		return;
	}

	const apply = e.target.closest('[data-fa-filters-columns-apply]');
	if (apply) {
		saveColumns(apply.closest('.modal'));
		return;
	}

	const remove = e.target.closest('[data-part="chip-remove"]');
	if (remove) {
		const chips = Array.from(remove.closest('.fa-filters-chips').children);
		pendingFocus = { grid: gridName(remove), chip: chips.indexOf(remove.closest('[data-part="chip"]')) };
		return;
	}

	const clearAll = e.target.closest('[data-part="clear-all"], [data-part="reset"]');
	if (clearAll) {
		pendingFocus = { grid: gridName(clearAll), bar: true };
	}
});

function gridName(el) {
	return el.closest('[data-datagrid-name]')?.dataset.datagridName ?? null;
}

// Přepínač výchozího filtru se odešle hned, signálem bez formuláře (pole filtru je v panelu sloupce)
document.addEventListener('change', (e) => {
	const toggle = e.target.closest('[data-fa-filters-default-toggle]');
	if (toggle) {
		pendingFocus = { id: toggle.id };
		naja.makeRequest('GET', toggle.dataset.faFiltersDefaultToggle, null, { history: false });
	}
});

document.addEventListener('submit', (e) => {
	const panel = e.submitter?.closest(PANEL);
	if (panel) {
		submittedFrom = panel.id;
	}
});

const PHONE = window.matchMedia('(max-width: 575.98px)');

// Na telefonu je panel spodní list přes celou obrazovku. V hlavičce tabulky by ho přerostly vrstvy lepivého
// řádku hlavičky, proto se po dobu otevření přesune na konec formuláře gridu - pole zůstávají ve formuláři.
// Dialog Sloupce: pořadí šipkami, viditelnost a šířka; uloží se až tlačítkem Použít
function moveColumn(button) {
	const item = button.closest('[data-fa-filters-column-key]');
	const sibling = button.dataset.faFiltersColumnMove === '-1' ? item.previousElementSibling : item.nextElementSibling;
	if (!sibling) {
		return;
	}
	if (button.dataset.faFiltersColumnMove === '-1') {
		sibling.before(item);
	} else {
		sibling.after(item);
	}
	markColumnEdges(item.parentElement);
	button.focus();
}

function markColumnEdges(list) {
	const items = Array.from(list.children);
	items.forEach((item, index) => {
		item.querySelector('[data-fa-filters-column-move="-1"]').disabled = index === 0;
		item.querySelector('[data-fa-filters-column-move="1"]').disabled = index === items.length - 1;
	});
}

function saveColumns(dialog) {
	const columns = Array.from(dialog.querySelectorAll('[data-fa-filters-column-key]'), (item) => ({
		key: item.dataset.faFiltersColumnKey,
		visible: item.querySelector('[data-fa-filters-column-visible]').checked,
		width: item.querySelector('[data-fa-filters-column-width]:checked')?.value ?? 'auto',
	}));
	dialog._faFiltersApplied = true;
	Modal.getOrCreateInstance(dialog).hide();
	naja.makeRequest('POST', dialog.dataset.faFiltersColumnsSave, { columns: JSON.stringify(columns) }, { history: false });
}

// Modal uvnitř gridu (pevně umístěný obsah stránky) by skončil pod zatmavením, po dobu otevření je v body.
// Zavření bez Použít vrátí dialog do stavu ze serveru.
document.addEventListener('show.bs.modal', (e) => {
	const dialog = e.target;
	if (!dialog.matches('[data-part="columns-dialog"]') || dialog.parentElement === document.body) {
		return;
	}
	dialog._faFiltersHome = { parent: dialog.parentElement, next: dialog.nextSibling };
	dialog._faFiltersList = dialog.querySelector('.fa-filters-columns').cloneNode(true);
	dialog._faFiltersApplied = false;
	document.body.append(dialog);
	markColumnEdges(dialog.querySelector('.fa-filters-columns'));
});

document.addEventListener('hidden.bs.modal', (e) => {
	const dialog = e.target;
	if (!dialog.matches('[data-part="columns-dialog"]') || !dialog._faFiltersHome) {
		return;
	}
	if (!dialog._faFiltersApplied) {
		dialog.querySelector('.fa-filters-columns').replaceWith(dialog._faFiltersList);
	}
	const { parent, next } = dialog._faFiltersHome;
	dialog._faFiltersHome = null;
	if (parent.isConnected) {
		parent.insertBefore(dialog, next);
	} else {
		dialog.remove();
	}
});

document.addEventListener('change', (e) => {
	const checkbox = e.target.closest('[data-fa-filters-column-visible]');
	if (checkbox) {
		checkbox.closest('[data-fa-filters-column-key]').classList.toggle('is-hidden', !checkbox.checked);
	}
});

function liftToForm(menu) {
	const form = menu.closest('form');
	if (!PHONE.matches || !form || menu.parentElement === form) {
		return;
	}
	menu._faFiltersHome = { parent: menu.parentElement, next: menu.nextSibling };
	form.append(menu);
	document.body.classList.add('fa-filters-sheet-open');
}

function returnHome(menu) {
	const home = menu._faFiltersHome;
	if (home) {
		home.parent.insertBefore(menu, home.next);
		menu._faFiltersHome = null;
	}
	if (!document.querySelector('.fa-filters-panel.show')) {
		document.body.classList.remove('fa-filters-sheet-open');
	}
}

function menuOf(toggle) {
	return Dropdown.getInstance(toggle)?._menu ?? toggle.parentElement.querySelector(':scope > .dropdown-menu');
}

document.addEventListener('show.bs.dropdown', (e) => {
	const panel = e.target.matches(TOGGLE) ? panelOf(e.target) : null;
	if (panel) {
		panel._faFiltersValues = snapshot(panel);
	}
	const menu = menuOf(e.target);
	if (menu?.matches('.fa-filters-panel')) {
		liftToForm(menu);
	}
});

document.addEventListener('hidden.bs.dropdown', (e) => {
	const menu = menuOf(e.target);
	if (menu?.matches('.fa-filters-panel')) {
		returnHome(menu);
	}
});

document.addEventListener('shown.bs.dropdown', (e) => {
	const panel = e.target.matches(TOGGLE) ? panelOf(e.target) : null;
	if (!panel) {
		return;
	}
	panel.querySelectorAll('[data-fa-filters-range]').forEach(initRange);
	// Nabídka Tom Selectu pod polem v toku panelu, ne uvnitř orámovaného pole (dropdownParent nejde předat atributem)
	panel.querySelectorAll('select.tomselected').forEach((select) => {
		const { wrapper, dropdown } = select.tomselect;
		if (dropdown.parentElement === wrapper) {
			wrapper.after(dropdown);
		}
	});
	const first = panel.querySelector('.fa-filters-field input:not([type="hidden"]):not([readonly]), .fa-filters-field select, .fa-filters-field .ts-control input');
	(first?.tomselect?.control_input ?? first)?.focus();
});

document.addEventListener('hide.bs.dropdown', (e) => {
	const panel = e.target.matches(TOGGLE) ? panelOf(e.target) : null;
	if (panel && panel.id !== submittedFrom && panel._faFiltersValues) {
		restore(panel, panel._faFiltersValues);
	}
});

// Enter v textovém poli panelu = Použít (formulář gridu má víc tlačítek, výchozí by bylo jiné)
document.addEventListener('keydown', (e) => {
	if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') {
		return;
	}
	const panel = e.target.closest(PANEL);
	if (panel && !e.target.closest('.ts-wrapper')) {
		e.preventDefault();
		submit(panel);
	}
});

// Panel uvnitř .table-responsive by se ořízl, Popper ho proto pozicuje vůči oknu
export function initColumnFilters($el) {
	$el[0].querySelectorAll(TOGGLE).forEach((toggle) => {
		Dropdown.getOrCreateInstance(toggle, {
			autoClose: 'outside',
			offset: [0, 8],
			popperConfig: (config) => ({ ...config, strategy: 'fixed' }),
		});
	});

	const revealed = $el[0].querySelector('[data-fa-filters-revealed]');
	if (revealed) {
		const table = revealed.closest('.datagrid')?.querySelector('table');
		table?.querySelectorAll(`.col-${CSS.escape(revealed.dataset.faFiltersRevealed)}`).forEach((cell) => cell.classList.add('fa-filters-col-new'));
	}

	if (pendingFocus) {
		const target = pendingFocus;
		setTimeout(() => {
			if (pendingFocus === target) {
				pendingFocus = null;
			}
			focusAfterRedraw(target);
		});
	}

	// Snippety gridu se překreslují postupně, hlavička je hotová až po posledním
	if (submittedFrom) {
		const id = submittedFrom;
		setTimeout(() => {
			const panel = document.getElementById(id);
			panel?.parentElement.querySelector(':scope > ' + TOGGLE)?.focus();
			if (submittedFrom === id) {
				submittedFrom = null;
			}
		});
	}
}

// Po odebrání čipu fokus na další čip, bez něj na Zrušit filtry; když zmizí celý pruh (nebo po Zrušit filtry), do lišty
function focusAfterRedraw(target) {
	if (target.id) {
		document.getElementById(target.id)?.focus();
		return;
	}

	const grid = document.querySelector(`[data-datagrid-name="${CSS.escape(target.grid ?? '')}"]`) ?? document;
	if (target.chip !== undefined) {
		const chips = grid.querySelectorAll('.fa-filters-chips > [data-part="chip"] > .fa-filters-chip-edit');
		const next = chips[Math.min(target.chip, chips.length - 1)] ?? grid.querySelector('[data-part="clear-all"]');
		if (next) {
			next.focus();
			return;
		}
	}

	const bar = grid.querySelector('[data-part="bar"]');
	bar?.querySelector('input:not([type="hidden"]), select, button, a[href]')?.focus();
}

onContentLoaded(initColumnFilters);
