// Popover sbaleného menu se nesmí dostat pod spodní okraj okna, jinak by spodní položky dlouhého podmenu nešly vybrat.
import { describe, expect, it } from 'vitest';
import { fitPopover } from '../../assets/js/popoverPosition.js';

describe('fitPopover', () => {
	it('popover, který se vejde, zůstává u své položky', () => {
		expect(fitPopover(300, 200, 900)).toEqual({ top: 300, bridgeTop: 0, scrollable: false });
	});

	it('dlouhý popover se posune nahoru tak, aby končil nad spodním okrajem', () => {
		// Nastavení e-shopu: 21 položek (867 px) od položky ve výšce 314 px v okně 900 px
		const { top, bridgeTop, scrollable } = fitPopover(314, 867, 900);

		expect(top).toBe(25);
		expect(top + 867).toBe(892);
		expect(bridgeTop).toBe(289);
		expect(scrollable).toBe(false);
	});

	it('popover vyšší než okno začíná u horního okraje a dostane posuvník', () => {
		expect(fitPopover(314, 867, 700)).toEqual({ top: 8, bridgeTop: 306, scrollable: true });
	});

	it('položka nad okrajem zůstává výš než okraj', () => {
		expect(fitPopover(4, 867, 700).top).toBe(4);
	});
});
