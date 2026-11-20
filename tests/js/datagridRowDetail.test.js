// Klik do řádku gridu přepíná detail pod ním (tr.row-item-detail), klik na odkaz, tlačítko nebo
// prvek formuláře v řádku ne - ani na ikonu uvnitř nich (event.target je pak <i>)
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	await import('../../assets/js/naja.js');
	await import('../../assets/js/datagrid.js');
});

beforeEach(() => {
	document.body.innerHTML = `<table><tbody>
		<tr data-id="1">
			<td class="col-name"><span class="text">Text</span> <a href="#detail"><i class="fa fa-building"></i> Firma</a></td>
			<td class="col-more"><button type="button" data-bs-toggle="dropdown"><i class="fa fa-ellipsis"></i></button>
				<div class="dropdown-menu"><span class="dropdown-item">Položka</span></div></td>
			<td class="col-check"><label><input type="checkbox"> Vybrat</label></td>
		</tr>
		<tr class="row-item-detail"><td colspan="3"><div class="item-detail-content">Detail</div></td></tr>
	</tbody></table>`;
});

const click = (selector) => document.querySelector(selector).dispatchEvent(new MouseEvent('click', { bubbles: true }));
const toggled = () => document.querySelector('tr.row-item-detail').classList.contains('toggled');

describe('detail řádku gridu', () => {
	it('klik do buňky detail rozbalí a další klik zase sbalí', () => {
		click('.col-name .text');
		expect(toggled()).toBe(true);

		click('.col-name .text');
		expect(toggled()).toBe(false);
	});

	it.each([
		['ikona v odkazu', '.col-name a i'],
		['odkaz', '.col-name a'],
		['ikona v tlačítku', '.col-more button i'],
		['položka rozbaleného menu', '.dropdown-item'],
		['popisek zaškrtávátka', '.col-check label'],
		['zaškrtávátko', '.col-check input'],
	])('%s detail nepřepne', (name, selector) => {
		click(selector);
		expect(toggled()).toBe(false);
	});
});
