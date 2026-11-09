import { defineConfig } from 'vitest/config';

// Testy JS (yarn test) - jsdom místo prohlížeče, bez buildu a bez pluginů z vite.config.mjs
export default defineConfig({
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
