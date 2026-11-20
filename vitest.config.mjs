import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Testy JS (yarn test) - jsdom místo prohlížeče, bez buildu a bez pluginů z vite.config.mjs
export default defineConfig({
	resolve: {
		// knihovny, které stránky tabletu berou z import mapy (@tablet.latte)
		alias: {
			pdfjs: fileURLToPath(new URL('tests/js/stubs/pdfjs.js', import.meta.url)),
			'@biometric-signature/biometric-signature.js': fileURLToPath(new URL('tests/js/stubs/biometricSignature.js', import.meta.url)),
		},
	},
	test: {
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'https://fancyadmin.test/connector',
			},
		},
		include: ['tests/js/**/*.test.js'],
	},
});
