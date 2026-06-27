// jQuery must be exposed as a global BEFORE any legacy jQuery plugin (jquery-ui-bundle,
// @regru/jquery-menu-aim, nette.ajax.js, …) is evaluated. ES module imports are hoisted
// and evaluated depth-first in source order, so importing this module FIRST guarantees
// the globals exist before those plugin modules run.
import jQuery from 'jquery';

window.$ = jQuery;
window.jquery = jQuery;
window.jQuery = jQuery;
globalThis.jQuery = jQuery;

export default jQuery;
