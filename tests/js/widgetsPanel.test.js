// Panel otevíraný odkazem jiné karty a vnořené řádky tabulky (assets/js/widgets.js): ve skupině je otevřený
// jen jeden panel, odkaz přepíná popisek a karta zvýrazněný okraj; řádek se šipkou ukáže své vnořené řádky.
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	await import('../../assets/js/widgets.js');
});

const tile = (target) => `<div class="card fa-widgets-toggler" data-widget-toggler="${target}">
	<div class="fa-widgets-toggle-foot"><button type="button" class="fa-widgets-toggle" data-widget-toggle="${target}" aria-expanded="false" data-open-label="Seznam nabídek" data-close-label="Skrýt seznam"><span class="fa-widgets-toggle-label">Seznam nabídek</span></button></div>
</div>`;
const panel = (name) => `<section data-widget="${name}" data-widget-panel="aging" hidden><button type="button" class="fa-widgets-panel-close" data-widget-toggle="${name}">Zavřít seznam</button></section>`;
const click = (element) => element.dispatchEvent(new MouseEvent('click', { bubbles: true }));

describe('panel otevíraný odkazem karty', () => {
	beforeEach(() => {
		document.body.innerHTML = tile('over90') + tile('over120') + panel('over90') + panel('over120');
		Element.prototype.scrollIntoView = () => {};
	});

	it('otevře panel, přepne popisek odkazu a zvýrazní kartu', () => {
		click(document.querySelector('.fa-widgets-toggle[data-widget-toggle="over90"]'));
		expect(document.querySelector('[data-widget="over90"]').hidden).toBe(false);
		const button = document.querySelector('.fa-widgets-toggle[data-widget-toggle="over90"]');
		expect(button.getAttribute('aria-expanded')).toBe('true');
		expect(button.textContent.trim()).toBe('Skrýt seznam');
		expect(document.querySelector('[data-widget-toggler="over90"]').classList.contains('fa-widgets-toggler-open')).toBe(true);
	});

	it('ve skupině je otevřený jen jeden panel', () => {
		click(document.querySelector('.fa-widgets-toggle[data-widget-toggle="over90"]'));
		click(document.querySelector('.fa-widgets-toggle[data-widget-toggle="over120"]'));
		expect(document.querySelector('[data-widget="over90"]').hidden).toBe(true);
		expect(document.querySelector('[data-widget="over120"]').hidden).toBe(false);
		expect(document.querySelector('.fa-widgets-toggle[data-widget-toggle="over90"]').textContent.trim()).toBe('Seznam nabídek');
	});

	it('Zavřít seznam panel skryje', () => {
		click(document.querySelector('.fa-widgets-toggle[data-widget-toggle="over90"]'));
		click(document.querySelector('[data-widget="over90"] .fa-widgets-panel-close'));
		expect(document.querySelector('[data-widget="over90"]').hidden).toBe(true);
		expect(document.querySelector('[data-widget-toggler="over90"]').classList.contains('fa-widgets-toggler-open')).toBe(false);
	});
});

describe('vnořené řádky tabulky', () => {
	beforeEach(() => {
		document.body.innerHTML = `<table><tbody>
			<tr data-widget-row="0"><td><button type="button" data-widget-row-toggle="0" aria-expanded="false"></button>Plzeň</td></tr>
			<tr data-widget-row-child="0" hidden><td>Adam</td></tr>
			<tr data-widget-row-child="0" hidden><td>Bára</td></tr>
			<tr data-widget-row="1"><td><button type="button" data-widget-row-toggle="1" aria-expanded="false"></button>Brno</td></tr>
			<tr data-widget-row-child="1" hidden><td>Cyril</td></tr>
		</tbody></table>`;
	});

	it('klik na řádek ukáže jen jeho vnořené řádky a druhý klik je skryje', () => {
		click(document.querySelector('tr[data-widget-row="0"] td'));
		expect([...document.querySelectorAll('[data-widget-row-child="0"]')].every((row) => !row.hidden)).toBe(true);
		expect(document.querySelector('[data-widget-row-child="1"]').hidden).toBe(true);
		expect(document.querySelector('[data-widget-row-toggle="0"]').getAttribute('aria-expanded')).toBe('true');
		click(document.querySelector('tr[data-widget-row="0"] td'));
		expect(document.querySelector('[data-widget-row-child="0"]').hidden).toBe(true);
	});
});
