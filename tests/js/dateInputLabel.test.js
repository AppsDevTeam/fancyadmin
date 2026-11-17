// S altInput je původní input skrytý; popisek musí patřit viditelnému poli.
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

beforeAll(async () => {
	document.body.innerHTML = `
		<label for="from">Platnost</label>
		<input id="from" type="text" aria-label="Platnost - od" data-flatpickr='{"dateFormat":"Y-m-d","altInput":true,"altFormat":"j.n.Y"}'>
		<label for="plain">Den</label>
		<input id="plain" type="date">
		<label for="time">Čas</label>
		<input id="time" type="time">`;

	const { initDateInput } = await import('../../assets/js/dateInput.js');
	initDateInput();
});

afterAll(() => {
	document.body.innerHTML = '';
});

describe('popisek pole s altInput', () => {
	it('aria-label a <label for> přejdou na viditelné pole', () => {
		const alt = document.getElementById('from')._flatpickr.altInput;

		expect(alt.getAttribute('aria-label')).toBe('Platnost - od');
		expect(alt.id).toBe('from-alt');
		expect(document.querySelector('label[for="from-alt"]').textContent).toBe('Platnost');
	});

	it('pole bez aria-label žádné nedostane, label se přesměruje', () => {
		const alt = document.getElementById('plain')._flatpickr.altInput;

		expect(alt.hasAttribute('aria-label')).toBe(false);
		expect(document.querySelector('label[for="plain-alt"]').textContent).toBe('Den');
	});

	it('pole bez altInput si popisek nechá', () => {
		expect(document.getElementById('time')._flatpickr.altInput).toBeFalsy();
		expect(document.querySelector('label[for="time"]').textContent).toBe('Čas');
	});
});
