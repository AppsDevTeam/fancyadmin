// Selecty uvnitř kalendáře flatpickru (měsíc, rok, hodiny, minuty) nesmí převzít Tom Select
// z adt-js-components, i když ho projekt pouští na všechny selecty - jeho seznam by se
// otvíral mimo kalendář. Drží je stranou třída select-default.
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

const tick = (ms = 0) => new Promise(resolve => setTimeout(resolve, ms));

async function waitFor(condition, timeout = 2000) {
	const start = Date.now();
	while (!condition()) {
		if (Date.now() - start > timeout) {
			throw new Error('Podmínka nenastala včas');
		}
		await tick(10);
	}
}

beforeAll(async () => {
	document.body.dataset.adtJsComponents = JSON.stringify({ select: { selector: 'select:not(.select-default)' } });
	document.body.innerHTML = `
		<select id="plain"><option>A</option><option>B</option></select>
		<input id="when" type="datetime-local" value="2026-11-17T09:30">`;

	const AdtJsComponents = (await import('adt-js-components')).default;
	AdtJsComponents.registerModules({});

	const { initDateInput } = await import('../../assets/js/dateInput.js');
	initDateInput({ time_24hr: true });

	await waitFor(() => document.getElementById('plain').tomselect);
	await tick(50);
});

afterAll(async () => {
	document.body.innerHTML = '';
	await tick();
});

describe('selecty kalendáře a Tom Select', () => {
	it('obyčejný select Tom Select dostane', () => {
		expect(document.getElementById('plain').tomselect).toBeTruthy();
	});

	it('select měsíce, roku a času zůstane nativní a nese select-default', () => {
		const selects = [
			...document.querySelectorAll('.flatpickr-monthDropdown-months, .flatpickr-yearDropdown-years, .flatpickr-timeDropdown'),
		];

		expect(selects.length).toBeGreaterThanOrEqual(4);
		selects.forEach((select) => {
			expect(select.classList.contains('select-default')).toBe(true);
			expect(select.tomselect).toBeUndefined();
			expect(select.nextElementSibling?.classList.contains('ts-wrapper') ?? false).toBe(false);
		});
	});

	// Kontrola, že kalendářové selecty drží stranou právě třída, ne jejich umístění
	it('select v kalendáři bez select-default Tom Select dostane', async () => {
		const select = document.createElement('select');
		select.innerHTML = '<option>X</option>';
		document.querySelector('.flatpickr-calendar').appendChild(select);

		await waitFor(() => select.tomselect);

		expect(select.tomselect).toBeTruthy();
	});
});
