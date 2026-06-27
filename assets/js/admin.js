// Standalone Vite entry for the FancyAdmin package — the equivalent of the old
// webpack 'admin' build (which bundled assets/js/app.js).
//
// app.js self-registers FancyAdmin's own components ('fancyadmin' scope). For the
// standalone build we additionally register the built-ins it uses, first, so the
// registration runs before app.js's init() calls (imports are hoisted).
import './_registerStandaloneBuiltins';
import './app';
