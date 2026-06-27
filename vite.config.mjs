// Standalone Vite build for the FancyAdmin package (replaces the old webpack.config.js).
// Consuming projects bundle FancyAdmin's assets from source via their own Vite config;
// this build is for developing/checking the package on its own. Output: dist/admin/.
import { defineConfig } from 'vite';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';
import inject from '@rollup/plugin-inject';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Copy fonts to where the compiled CSS references them. FontAwesome's @font-face uses
// its built-in "../webfonts" default and the Roboto faces use "../fonts" — both relative
// to the emitted CSS in dist/admin/, i.e. dist/{webfonts,fonts}.
const copyFonts = () => ({
	name: 'copy-fancyadmin-fonts',
	apply: 'build',
	writeBundle() {
		const copies = [
			['node_modules/@fortawesome/fontawesome-pro/webfonts', 'dist/webfonts'],
			['assets/fonts', 'dist/fonts'],
		];
		for (const [from, to] of copies) {
			const src = path.resolve(__dirname, from);
			const dest = path.resolve(__dirname, to);
			if (fs.existsSync(src)) {
				fs.cpSync(src, dest, { recursive: true });
			}
		}
	},
});

export default defineConfig(({ mode }) => {
	const isProduction = mode === 'production';

	return {
		root: path.resolve(__dirname, 'assets'),

		build: {
			outDir: path.resolve(__dirname, 'dist/admin'),
			assetsDir: '',
			emptyOutDir: true,
			sourcemap: !isProduction,
			minify: isProduction ? 'esbuild' : false,
			cssMinify: isProduction,
			manifest: true,
			rollupOptions: {
				input: path.resolve(__dirname, 'assets/js/admin.js'),
			},
		},

		server: {
			https: false,
			host: 'localhost',
			port: 3002,
			strictPort: true,
		},

		css: {
			devSourcemap: !isProduction,
			preprocessorOptions: {
				scss: {
					quietDeps: true,
					api: 'modern-compiler',
					loadPaths: [
						path.resolve(__dirname, 'node_modules'),
					],
				},
			},
			modules: {
				generateScopedName: isProduction
					? '[hash:base64]'
					: '[path][local]__[hash:base64:5]',
				localsConvention: 'camelCase',
			},
			postcss: {
				plugins: [autoprefixer()],
			},
		},

		plugins: [
			// Inject jQuery globally (replaces webpack ProvidePlugin / expose-loader).
			// select2's UMD self-registers on the global jQuery, so don't rewrite it there.
			inject({
				include: ['**/*.js'],
				exclude: [/select2/],
				$: 'jquery',
				jQuery: 'jquery',
			}),

			copyFonts(),
		],

		optimizeDeps: {
			include: ['jquery'],
		},

		define: {
			'process.env.NODE_ENV': JSON.stringify(mode),
		},
	};
});
