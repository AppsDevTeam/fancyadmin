// Registers FancyAdmin's own JS components (referenced as "~UI/..." in
// AdtJsComponents.init() calls), so each consuming project doesn't have to.
//
// Imported first by app.js — runs before app.js's init() calls, and merges into
// the shared registry (see ComponentLoader.registerModules), so the consumer can
// still register its own 'app' components and 'builtin' allowlist separately.
//
// The glob is relative to this file (assets/js/) → resolves to the package's src/UI.
import AdtJsComponents from 'adt-js-components';

AdtJsComponents.registerModules({
	fancyadmin: import.meta.glob('../../src/UI/**/index.js'),
});
