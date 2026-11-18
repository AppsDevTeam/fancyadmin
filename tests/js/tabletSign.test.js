// Stránka podpisu dokumentů na tabletu (assets/js/tabletSign.js, createSignState): podpis
// nakreslený jednou jde potvrdit do dalších míst, nový podpis platí jen pro místo, kde vznikl,
// a odeslat jde až všechna místa všech dokumentů.
// (pdf.js a plátno z import mapy nahrazují stuby, vitest.config.mjs)
import { describe, expect, it } from 'vitest';
import { createSignState } from '../../assets/js/tabletSign.js';

const PLACES = [
	{ id: 'contract:0', document: 'contract' },
	{ id: 'contract:2', document: 'contract' },
	{ id: 'consent:-', document: 'consent' },
];
const FIRST = { image: 'data:image/png;base64,AAA', biometrics: '{"v":1}' };
const SECOND = { image: 'data:image/png;base64,BBB', biometrics: '{"v":2}' };

describe('createSignState', () => {
	it('převezme předchozí podpis do dalšího místa a odešle ho jednou', () => {
		const state = createSignState(PLACES);
		expect(state.getLast('contract:2')).toBeNull();

		state.sign('contract:0', FIRST);
		expect(state.getLast('contract:2')).toEqual(FIRST);
		state.reuse('contract:2');
		state.sign('consent:-', SECOND);

		expect(state.isComplete()).toBe(true);
		expect(state.toJSON()).toEqual({
			signatures: { s1: FIRST, s2: SECOND },
			placements: { 'contract:0': 's1', 'contract:2': 's1', 'consent:-': 's2' },
		});
	});

	it('nový podpis platí jen pro své místo, přepsaný podpis se neodesílá', () => {
		const state = createSignState(PLACES);
		state.sign('contract:0', FIRST);
		state.reuse('contract:2');

		// podepsal se znovu jen do druhého místa
		state.sign('contract:2', SECOND);
		expect(state.get('contract:0')).toEqual(FIRST);
		expect(state.get('contract:2')).toEqual(SECOND);
		expect(state.getLast('consent:-')).toEqual(SECOND);

		// a pak znovu i do prvního - první podpis už v žádném místě není
		state.sign('contract:0', SECOND);
		expect(Object.keys(state.toJSON().signatures)).toEqual(['s3', 's2']);
	});

	it('počítá podepsaná místa celkem i po dokumentech a najde další nepodepsané', () => {
		const state = createSignState(PLACES);
		expect(state.progress()).toEqual({ signed: 0, total: 3 });
		expect(state.nextUnsigned()).toEqual(PLACES[0]);

		state.sign('contract:2', FIRST);
		expect(state.progress('contract')).toEqual({ signed: 1, total: 2 });
		expect(state.progress('consent')).toEqual({ signed: 0, total: 1 });
		expect(state.isComplete()).toBe(false);

		// od naposledy ukázaného místa dál, pak znovu od začátku
		expect(state.nextUnsigned('contract:0')).toEqual(PLACES[2]);
		expect(state.nextUnsigned('consent:-')).toEqual(PLACES[0]);
	});

	it('podpis jednoho podepisujícího se druhému nenabídne', () => {
		const state = createSignState([
			{ id: 'contract:0', document: 'contract', signer: 'Jan Novák' },
			{ id: 'contract:1', document: 'contract', signer: 'Eva Nováková' },
			{ id: 'consent:-', document: 'consent', signer: 'Jan Novák' },
		]);
		state.sign('contract:0', FIRST);

		expect(state.getLast('contract:1')).toBeNull();
		expect(() => state.reuse('contract:1')).toThrow();
		expect(state.getLast('consent:-')).toEqual(FIRST);

		state.sign('contract:1', SECOND);
		state.reuse('consent:-');
		expect(state.get('consent:-')).toEqual(FIRST);
	});

	it('převzít jde až nějaký podpis', () => {
		expect(() => createSignState(PLACES).reuse('contract:0')).toThrow();
	});
});
