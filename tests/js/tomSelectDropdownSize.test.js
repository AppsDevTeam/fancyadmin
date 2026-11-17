// Seznam Tom Selectu zavěšený mimo pole (dropdownParent: 'body') přebírá velikost pole,
// ze kterého se otevřel - jinak by malý filtr otevřel seznam s položkami velikosti běžného pole.
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

let TomSelect;
let stopObserving;

const nextTick = () => new Promise(resolve => setTimeout(resolve));

function createSelect(id, size) {
	const container = document.createElement('div');
	container.innerHTML = `<select id="${id}" class="form-select"><option>A</option><option>B</option></select>`;
	document.body.appendChild(container);
	const tomSelect = new TomSelect(container.querySelector('select'), { dropdownParent: 'body' });
	Object.entries(size).forEach(([name, value]) => tomSelect.wrapper.style.setProperty(name, value));
	return tomSelect;
}

beforeAll(async () => {
	TomSelect = (await import('tom-select')).default;
	({ stopObserving } = await import('../../assets/js/tomSelectDropdownSize.js'));
});

// Sdílený MutationObserver adt-js-components by jinak zpracovával poslední změny DOM
// (zavření seznamů) až při úklidu jsdom, kdy už neexistuje globál Node
afterAll(async () => {
	stopObserving();
	document.body.innerHTML = '';
	await nextTick();
});

describe('velikost seznamu Tom Selectu', () => {
	it('převezme proměnné škály z pole při otevření', async () => {
		const tomSelect = createSelect('small', {
			'--control-height': '32px',
			'--control-padding-x': '12px',
			'--control-font': '14px',
		});
		await nextTick();

		tomSelect.open();

		expect(tomSelect.dropdown.parentElement).toBe(document.body);
		expect(tomSelect.dropdown.style.getPropertyValue('--control-height')).toBe('32px');
		expect(tomSelect.dropdown.style.getPropertyValue('--control-padding-x')).toBe('12px');
		expect(tomSelect.dropdown.style.getPropertyValue('--control-font')).toBe('14px');
	});

	it('každý seznam dostane velikost svého pole', async () => {
		const small = createSelect('a', { '--control-height': '32px' });
		const large = createSelect('b', { '--control-height': '48px' });
		await nextTick();

		small.open();
		large.open();

		expect(small.dropdown.style.getPropertyValue('--control-height')).toBe('32px');
		expect(large.dropdown.style.getPropertyValue('--control-height')).toBe('48px');
	});

	it('pole bez proměnných nechá seznamu výchozí hodnoty ze SCSS', async () => {
		const plain = createSelect('plain', {});
		await nextTick();

		plain.open();

		expect(plain.dropdown.style.getPropertyValue('--control-height')).toBe('');
	});
});
