// Popover sbaleného menu se nesmí dostat pod spodní okraj okna, jinak by spodní položky dlouhého podmenu nešly vybrat.
import { describe, expect, it } from 'vitest';
import { fitPopover, isAimingAt } from '../../assets/js/popoverPosition.js';

describe('fitPopover', () => {
	it('popover, který se vejde, zůstává u své položky', () => {
		expect(fitPopover(300, 200, 900)).toEqual({ top: 300, scrollable: false });
	});

	it('dlouhý popover se posune nahoru tak, aby končil nad spodním okrajem', () => {
		// Nastavení e-shopu: 21 položek (867 px) od položky ve výšce 314 px v okně 900 px
		const { top, scrollable } = fitPopover(314, 867, 900);

		expect(top).toBe(25);
		expect(top + 867).toBe(892);
		expect(scrollable).toBe(false);
	});

	it('popover vyšší než okno začíná u horního okraje a dostane posuvník', () => {
		expect(fitPopover(314, 867, 700)).toEqual({ top: 8, scrollable: true });
	});

	it('položka nad okrajem zůstává výš než okraj', () => {
		expect(fitPopover(4, 867, 700).top).toBe(4);
	});
});

// Šikmý přejezd z položky do jejího popoveru vede přes položky pod ní - ty ho nesmí přepnout.
describe('isAimingAt', () => {
	// Sbalené menu 68 px, popover položky ve výšce 100 px
	const popover = { left: 68, top: 100, bottom: 500 };

	it('šikmo dolů doprava do popoveru', () => {
		expect(isAimingAt({ x: 34, y: 120 }, { x: 44, y: 150 }, popover)).toBe(true);
	});

	it('vodorovně doprava', () => {
		expect(isAimingAt({ x: 34, y: 120 }, { x: 44, y: 120 }, popover)).toBe(true);
	});

	it('svisle dolů na další položku nemíří', () => {
		expect(isAimingAt({ x: 34, y: 120 }, { x: 34, y: 170 }, popover)).toBe(false);
	});

	it('doleva nemíří', () => {
		expect(isAimingAt({ x: 34, y: 120 }, { x: 24, y: 150 }, popover)).toBe(false);
	});

	it('doprava pod spodní okraj popoveru nemíří', () => {
		expect(isAimingAt({ x: 34, y: 120 }, { x: 44, y: 300 }, popover)).toBe(false);
	});

	it('myš už v popoveru nemíří', () => {
		expect(isAimingAt({ x: 80, y: 120 }, { x: 90, y: 150 }, popover)).toBe(false);
	});
});
