// Potvrzení akce gridu (data-datagrid-confirm): odkazy jdou přes Naja i bez třídy .ajax, potvrzení se ptá
// jednou (rozšíření datagrid.confirm); starý jQuery handler zůstává jen pro odkazy, které Naja nepřebírá
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

let asked;

function link(attrs) {
	document.body.innerHTML = `<div class="datagrid"><a href="/admin/settings/product?do=grid-duplicate" ${attrs} data-datagrid-confirm="Opravdu?">Duplikovat</a></div>`;
	return document.querySelector('a');
}

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	await import('../../assets/js/naja.js');
	await import('../../assets/js/datagrid/datagrid.js');
});

beforeEach(() => {
	asked = [];
	window.confirm = (message) => {
		asked.push(message);
		return false;
	};
});

describe('potvrzení akce gridu', () => {
	it('odkaz bez .ajax, který přebírá Naja, se ptá jednou', () => {
		link('class="btn btn-secondary"').click();

		expect(asked).toEqual(['Opravdu?']);
	});

	it('odkaz s .ajax se ptá jednou', () => {
		link('class="ajax datagrid-delete"').click();

		expect(asked).toEqual(['Opravdu?']);
	});

	it('odkaz .noajax (mimo Naja) se ptá jednou a po zrušení nikam nejde', () => {
		const event = new MouseEvent('click', { bubbles: true, cancelable: true });
		link('class="noajax"').dispatchEvent(event);

		expect(asked).toEqual(['Opravdu?']);
		expect(event.defaultPrevented).toBe(true);
	});
});
