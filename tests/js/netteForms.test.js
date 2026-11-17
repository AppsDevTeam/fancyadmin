// nette-forms smí být na stránce jen jedna - ta z builtinu Form adt-js-components. Druhá kopie by
// formuláře inicializovala dřív a chyby u polí z builtinu by se při odeslání nepoužily.
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// import.meta.url je v prostředí jsdom adresa stránky, testy běží z kořene balíčku
const root = (path) => resolve(process.cwd(), path);
const read = (path) => readFileSync(root(path), 'utf8');

describe('nette-forms', () => {
	it('app.js ho neimportuje ani nenastavuje window.Nette', () => {
		const app = read('assets/js/app.js');

		expect(app).not.toMatch(/from\s+['"]nette-forms['"]/);
		expect(app).not.toMatch(/import\s+['"]nette-forms['"]/);
		expect(app).not.toMatch(/window\.Nette\s*=/);
	});

	it('není přímou závislostí balíčku', () => {
		expect(JSON.parse(read('package.json')).dependencies).not.toHaveProperty('nette-forms');
	});

	it('jediná kopie je ta, kterou importuje builtin Form', () => {
		const require = createRequire(root('node_modules/adt-js-components/src/Form/index.js'));

		expect(require.resolve('nette-forms')).toBe(createRequire(root('package.json')).resolve('nette-forms'));
	});
});
