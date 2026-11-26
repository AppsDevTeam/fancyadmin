// Filtr v hlavičce sloupce (ColumnFiltersTrait, docs/datagrid-column-filters.md). Panel je Bootstrap dropdown,
// pole se odesílají až tlačítkem Použít. Zavření panelu bez něj vrátí pole na hodnoty ze serveru, takže stav
// filtrů se vždy vykreslí ze serveru a v prohlížeči žije jen otevřeno/zavřeno.

import { Dropdown } from 'bootstrap';
import flatpickr from 'flatpickr';

import { onContentLoaded } from './naja';

const TOGGLE = '[data-part="toggle"], [data-part="more"], .fa-filters-chip.dropdown > .fa-filters-chip-edit';
const PANEL = '[data-fa-filters-panel]';

// panel, jehož Použít právě odeslal formulář - po překreslení se fokus vrátí na jeho hlavičku
let submittedFrom = null;

let calendarLocale = {};

// Jazyk kalendáře období, stejně jako initDateInput: configureColumnFilters({ locale: Czech })
export function configureColumnFilters({ locale } = {}) {
	calendarLocale = locale ?? {};
}

function panelOf(toggle) {
	return toggle.parentElement.querySelector(':scope > ' + PANEL);
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
			el.checked = el.value === '';
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
	}
});

document.addEventListener('submit', (e) => {
	const panel = e.submitter?.closest(PANEL);
	if (panel) {
		submittedFrom = panel.id;
	}
});

document.addEventListener('show.bs.dropdown', (e) => {
	const panel = e.target.matches(TOGGLE) ? panelOf(e.target) : null;
	if (panel) {
		panel._faFiltersValues = snapshot(panel);
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

onContentLoaded(initColumnFilters);
