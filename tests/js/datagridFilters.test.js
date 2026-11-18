// Tlačítko Filtry bez pole Hledat (DataGridFilters.latte): knihovna kreslí řádek filtrů rozbalený,
// sbalení podle stavu ze serveru převezme JS a dál ho řídí Bootstrap collapse
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

let naja;

function grid(collapsed) {
	return `<div id="snippet-grid-grid"><form>
		<div class="grid-filters-bar${collapsed ? ' grid-filters-bar--collapsed' : ''}">
			<button type="button" class="btn btn-outline-primary grid-filters-toggle${collapsed ? ' collapsed' : ''}" data-bs-toggle="collapse" data-bs-target=".grid-filters-orderGrid" aria-expanded="${collapsed ? 'false' : 'true'}">Filtry</button>
		</div>
		<div class="grid-filters-orderGrid collapse show"><div class="row-filters"></div></div>
	</form></div>`;
}

const filters = () => document.querySelector('.grid-filters-orderGrid');
const bar = () => document.querySelector('.grid-filters-bar');

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	({ default: naja } = await import('../../assets/js/naja.js'));
	await import('../../assets/js/datagrid.js');
});

beforeEach(() => {
	document.body.innerHTML = '';
});

describe('sbalené filtry gridu', () => {
	it('neaktivní filtr: řádek ztratí show a lišta stav ze serveru', async () => {
		const { initCollapsedFilters } = await import('../../assets/js/datagrid.js');
		document.body.innerHTML = grid(true);
		initCollapsedFilters($(document));

		expect(filters().classList.contains('show')).toBe(false);
		expect(filters().classList.contains('collapse')).toBe(true);
		expect(bar().classList.contains('grid-filters-bar--collapsed')).toBe(false);
	});

	it('aktivní filtr: řádek zůstane rozbalený', async () => {
		const { initCollapsedFilters } = await import('../../assets/js/datagrid.js');
		document.body.innerHTML = grid(false);
		initCollapsedFilters($(document));

		expect(filters().classList.contains('show')).toBe(true);
	});

	it('překreslený grid (snippet) se sbalí znovu', () => {
		document.body.innerHTML = grid(false);
		const snippet = document.getElementById('snippet-grid-grid');
		snippet.innerHTML = grid(true);
		naja.snippetHandler.dispatchEvent(new CustomEvent('afterUpdate', { detail: { snippet } }));

		expect(filters().classList.contains('show')).toBe(false);
	});

	it('řádek filtrů jiného gridu na stránce nesbalí', async () => {
		const { initCollapsedFilters } = await import('../../assets/js/datagrid.js');
		document.body.innerHTML = grid(true) + '<form><div class="grid-filters-otherGrid collapse show"></div></form>';
		initCollapsedFilters($(document));

		expect(document.querySelector('.grid-filters-otherGrid').classList.contains('show')).toBe(true);
	});
});
