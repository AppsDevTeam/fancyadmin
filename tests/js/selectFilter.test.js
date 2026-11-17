// Filtr (.select-filter) má šířku podle nejdelší volby, aby se při přepínání neměnil, a to
// i ve skryté záložce nebo když přijde až v překresleném snippetu.
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

let fit;
let stopObserving;

const nextTick = () => new Promise(resolve => setTimeout(resolve));

// jsdom canvas neumí; měření nahrazuje 8 px na znak
function mockCanvas() {
	window.HTMLCanvasElement.prototype.getContext = () => ({
		font: '',
		measureText: (text) => ({ width: text.length * 8 }),
	});
}

function filter(id, options, hidden = false) {
	const form = document.createElement('form');
	form.className = 'select-filter';
	form.hidden = hidden;
	form.innerHTML = `<label class="form-label" for="${id}">Filtr</label><select id="${id}" class="form-select form-select-sm">${options.map(o => `<option>${o}</option>`).join('')}</select>`;
	return form;
}

const textWidth = (id) => document.getElementById(id).closest('.select-filter').style.getPropertyValue('--select-filter-text-width');

beforeAll(async () => {
	mockCanvas();
	document.body.append(filter('initial', ['Vše', 'Objednávky', 'Zákazníci']));
	({ fit, stopObserving } = await import('../../assets/js/selectFilter.js'));
	await nextTick();
});

afterAll(async () => {
	stopObserving();
	document.body.innerHTML = '';
	await nextTick();
});

describe('šířka filtru', () => {
	it('změří nejdelší volbu filtru, který už na stránce je', () => {
		expect(textWidth('initial')).toBe(`${'Objednávky'.length * 8}px`);
	});

	it('změří i filtr ve skryté záložce', async () => {
		document.body.append(filter('hidden', ['Vše', 'Velmi dlouhá volba filtru'], true));
		await nextTick();

		expect(textWidth('hidden')).toBe(`${'Velmi dlouhá volba filtru'.length * 8}px`);
	});

	it('změří filtr přidaný později (překreslený snippet)', async () => {
		const snippet = document.createElement('div');
		snippet.append(filter('snippet', ['A', 'BB']));
		document.body.append(snippet);
		await nextTick();

		expect(textWidth('snippet')).toBe('16px');
	});

	it('po změně voleb přepočítá šířku', () => {
		const select = document.getElementById('initial');
		select.add(new Option('Nejdelší volba ze všech'));

		fit(select);

		expect(textWidth('initial')).toBe(`${'Nejdelší volba ze všech'.length * 8}px`);
	});
});
