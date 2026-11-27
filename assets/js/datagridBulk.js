// Plovoucí lišta hromadných akcí gridu (DataGridColumnFilters.latte, blok bulk-bar, docs/datagrid-column-filters.md).
// Ukáže se po zaškrtnutí řádku, na stránce s rychlou lištou místo ní (body.fa-bulk-active). Tlačítka akcí jen nastaví
// skrytý výběr akce knihovny a odešlou formulář gridu stejně jako dřív select + Provést.
//
// Výběr přežije načtení dalších řádků (nekonečné stránkování překreslí celé tělo tabulky), každé jiné překreslení
// gridu (filtr, hledání, provedená akce) ho zruší - vybrané řádky by jinak mohly zmizet z pohledu.

import naja from 'naja';
import { Modal } from 'bootstrap';

const BAR = '[data-part="bulk-bar"]';
const ACTIVE_CLASS = 'fa-bulk-active';

// grid (plné jméno) → id vybraných řádků
const selections = new Map();
const lastChecked = new Map();
let touchedGrids = new Set();

function barOf(grid) {
	return document.querySelector(`${BAR}[data-fa-bulk="${CSS.escape(grid)}"]`);
}

function rootOf(grid) {
	return document.querySelector(`[data-datagrid-name="${CSS.escape(grid)}"]`);
}

function rowInputs(grid) {
	return rootOf(grid)?.querySelectorAll(`input[data-check-all-${CSS.escape(grid)}]`) ?? [];
}

function rowId(input) {
	return input.name.match(/\[([^\]]+)\]$/)?.[1] ?? null;
}

// Čeština a slovenština: 1 / 2-4 / 5+ (i 0)
function countLabel(bar, count) {
	const form = count === 1 ? 'one' : (count >= 2 && count <= 4 ? 'few' : 'many');
	return (bar.dataset[`count${form[0].toUpperCase()}${form.slice(1)}`] ?? '').replace('%count%', count);
}

function render(grid) {
	const bar = barOf(grid);
	if (!bar) {
		return;
	}
	const selected = selections.get(grid) ?? new Set();
	const inputs = [...rowInputs(grid)];
	inputs.forEach((input) => input.closest('tr')?.classList.toggle('table-active', input.checked));

	const toggle = rootOf(grid)?.querySelector(`input[data-check-all="${CSS.escape(grid)}"]`);
	if (toggle) {
		const checked = inputs.filter((input) => input.checked).length;
		toggle.checked = checked > 0 && checked === inputs.length;
		toggle.indeterminate = checked > 0 && checked < inputs.length;
	}

	const label = countLabel(bar, selected.size);
	document.querySelectorAll(`[data-fa-bulk="${CSS.escape(grid)}"] [data-part="bulk-count"], [id^="${CSS.escape(grid)}-bulk-"] [data-part="bulk-count"]`)
		.forEach((el) => { el.textContent = label; });
	bar.hidden = selected.size === 0;
	document.body.classList.toggle(ACTIVE_CLASS, !!document.querySelector(`${BAR}:not([hidden])`));
}

function readSelection(grid) {
	selections.set(grid, new Set([...rowInputs(grid)].filter((input) => input.checked).map(rowId)));
	render(grid);
}

function restoreSelection(grid) {
	const selected = selections.get(grid) ?? new Set();
	rowInputs(grid).forEach((input) => { input.checked = selected.has(rowId(input)); });
	readSelection(grid);
}

function clearSelection(grid) {
	rowInputs(grid).forEach((input) => { input.checked = false; });
	selections.set(grid, new Set());
	render(grid);
}

function gridOfInput(input) {
	return input.dataset.check ?? null;
}

// Zaškrtávátka mění i knihovna (Vybrat vše, výběr se Shiftem), proto se stav čte až po ní
document.addEventListener('change', (e) => {
	const grid = e.target.matches?.('input[data-check]') ? gridOfInput(e.target) : null;
	if (grid && barOf(grid)) {
		if (!e.target.hasAttribute('data-check-all') && e.target.checked) {
			lastChecked.set(grid, e.target);
		}
		setTimeout(() => readSelection(grid));
	}
});

document.addEventListener('click', (e) => {
	const checkbox = e.target.closest?.('.col-checkbox');
	const input = checkbox?.querySelector('input[data-check]');
	if (input && barOf(gridOfInput(input))) {
		setTimeout(() => readSelection(gridOfInput(input)));
	}

	const clear = e.target.closest('[data-part="bulk-clear"]');
	if (clear) {
		clearSelection(clear.closest(BAR).dataset.faBulk);
		return;
	}

	const action = e.target.closest('[data-part="bulk-action"]');
	if (action) {
		runAction(action.dataset.faBulkGrid, action.dataset.faBulkAction, action.dataset.faBulkType);
		return;
	}

	const submit = e.target.closest('[data-fa-bulk-submit]');
	if (submit) {
		const dialog = submit.closest('[data-part="bulk-dialog"]');
		Modal.getInstance(dialog)?.hide();
		submitAction(submit.dataset.faBulkGrid);
	}
});

function formOf(grid) {
	return barOf(grid)?.closest('form') ?? null;
}

function runAction(grid, id, type) {
	const form = formOf(grid);
	if (!form) {
		return;
	}
	if (type === 'button') {
		form.requestSubmit(form.elements[`group_action[${id}]`]);
		return;
	}

	const select = form.elements['group_action[group_action]'];
	select.disabled = false;
	select.value = id;
	// Nette podle zvolené akce ukáže její doplňující pole (toggle)
	window.Nette?.toggleForm?.(form);

	if (type === 'input') {
		const dialog = barOf(grid).querySelector(`[data-part="bulk-dialog"][data-fa-bulk-action="${CSS.escape(id)}"]`)
			?? document.querySelector(`body > [data-part="bulk-dialog"][data-fa-bulk-grid="${CSS.escape(grid)}"][data-fa-bulk-action="${CSS.escape(id)}"]`);
		Modal.getOrCreateInstance(dialog).show();
		return;
	}

	submitAction(grid);
}

function submitAction(grid) {
	const form = formOf(grid);
	form?.requestSubmit(form.elements['group_action[submit]']);
}

// Okno uvnitř gridu (pevně umístěný obsah stránky) by skončilo pod zatmavením, po dobu otevření je v body.
// Pole akce patří formuláři gridu přes atribut form, takže se odešle i odtud.
document.addEventListener('show.bs.modal', (e) => {
	const dialog = e.target;
	if (!dialog.matches('[data-part="bulk-dialog"]') || dialog.parentElement === document.body) {
		return;
	}
	dialog._faBulkHome = { parent: dialog.parentElement, next: dialog.nextSibling };
	document.body.append(dialog);
});

document.addEventListener('hidden.bs.modal', (e) => {
	const dialog = e.target;
	if (!dialog.matches('[data-part="bulk-dialog"]') || !dialog._faBulkHome) {
		return;
	}
	const { parent, next } = dialog._faBulkHome;
	dialog._faBulkHome = null;
	if (parent.isConnected) {
		parent.insertBefore(dialog, next);
	} else {
		dialog.remove();
	}
});

// Esc v liště zruší výběr a vrátí fokus na naposledy zaškrtnutý řádek
document.addEventListener('keydown', (e) => {
	const bar = e.key === 'Escape' ? e.target.closest?.(BAR) : null;
	if (!bar || e.target.closest('.dropdown-menu.show')) {
		return;
	}
	const grid = bar.dataset.faBulk;
	const last = lastChecked.get(grid);
	clearSelection(grid);
	(last?.isConnected ? last : rowInputs(grid)[0])?.focus();
});

naja.snippetHandler.addEventListener('afterUpdate', (e) => {
	const root = e.detail.snippet.closest('[data-datagrid-name]');
	if (root) {
		touchedGrids.add(root.dataset.datagridName);
	}
});

naja.addEventListener('complete', (e) => {
	const element = e.detail.options?.element;
	const loadMore = element?.matches?.('[data-datagrid-infinite-scroll]');
	const bulkSubmit = typeof element?.name === 'string' && element.name.startsWith('group_action[');
	const grids = touchedGrids;
	touchedGrids = new Set();

	grids.forEach((grid) => {
		if (!barOf(grid)) {
			return;
		}
		if (loadMore && !bulkSubmit) {
			restoreSelection(grid);
		} else {
			clearSelection(grid);
		}
	});

	if (bulkSubmit) {
		const grid = element.form?.querySelector(BAR)?.dataset.faBulk;
		if (grid) {
			clearSelection(grid);
		}
	}
});
