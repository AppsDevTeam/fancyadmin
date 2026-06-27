// Built-in adt-js-components used by FancyAdmin's own standalone build (admin.js).
// In a real consuming project this allowlist lives in the project; here it's only
// for the package's standalone build. Separate module so it's evaluated before
// app.js's init() calls (static imports are hoisted, so a registerModules() call in
// admin.js's body would run too late).
import AdtJsComponents from 'adt-js-components';

AdtJsComponents.registerModules({
	builtin: import.meta.glob('../../node_modules/adt-js-components/src/{Messaging,Notifications,Translate}/index.js'),
});
