const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const Autoprefixer = require('autoprefixer');
const PostCssModules = require("postcss-modules");
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const CssMinimizerWebpackPlugin = require("css-minimizer-webpack-plugin");
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

const SUPPORTED_LANGS = /cs/;

module.exports = (env) => {
	const isProduction = false;//process.env.NODE_ENV;
	const module = 'admin';
	const entryPoints = {
		admin: './src/assets/js/app.js',
	};

	const config = {
		entry: {
			[module]: entryPoints[module] // Dynamicky přidáme pouze vybraný entry point
		},

		output: {
			path: path.resolve(__dirname, 'dist', module), // každý modul bude mít svůj adresář
			publicPath: `/dist/${module}/`,
			filename: 'js/[name].js',
			chunkFilename: 'js/[name].[hash:5].js'
		},

		// Development or production?
		mode: process.env.NODE_ENV,

		// Source map generation
		devtool: isProduction ? false : 'eval-cheap-module-source-map',

		module: {
			rules: [
				{
					// Exposes jQuery for use outside Webpack build
					test: require.resolve('jquery'),
					loader: 'expose-loader',
					options: {
						exposes: ['jQuery', '$', 'jquery']
					}
				},
				{
					test: /\.js$/,
					exclude: /(node_modules)/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: ['@babel/preset-env'],
							plugins: ['@babel/plugin-proposal-class-properties']
						}
					}
				},
				{
					test: /\.tsx?$/,
					use: 'raw-loader',
				},
				{
					test: /\.module.scss$/,
					use: [{
						loader: MiniCssExtractPlugin.loader,
					}, {
						loader: 'css-loader', // translates CSS into CommonJS modules
						options: {
							sourceMap: !isProduction,
							modules: {
								localIdentName: isProduction ? '[hash:base64]' : '[path][local]__[hash:base64:5]',
							},
						}
					}, {
						loader: 'postcss-loader', // Run post css actions
						options: {
							sourceMap: !isProduction,
							plugins: function () { // post css plugins, can be exported to postcss.config.js
								return [
									PostCssModules({
										generateScopedName: isProduction ? '[hash:base64]' : '[path][local]__[hash:base64:5]',
									}),
									Autoprefixer,
								];
							}
						}
					}, {
						loader: 'sass-loader', // compiles Sass to CSS
						options: {
							sourceMap: !isProduction,
						}
					}]
				},
				{
					test: /\.(scss)$/,
					exclude: /\.module.scss$/,
					use: [{
						loader: MiniCssExtractPlugin.loader,
					}, {
						loader: 'css-loader', // translates CSS into CommonJS modules
						options: {
							sourceMap: !isProduction,
						}
					}, {
						loader: 'postcss-loader', // Run post css actions
						options: {
							sourceMap: !isProduction,
							postcssOptions: {
								plugins: function () { // post css plugins, can be exported to postcss.config.js
									return [
										// require('precss'),
										Autoprefixer,
									];
								}
							}
						}
					}, {
						loader: 'sass-loader', // compiles Sass to CSS
						options: {
							sourceMap: !isProduction,
						}
					}]
				},
				{
					test: /\.(css)$/,
					use: [
						'style-loader',
						'css-loader',
					]
				},
				{
					test: /\.(ttf|woff|woff2|eot|svg)$/i,
					type: 'asset/resource',
					generator: {
						filename: 'fonts/[hash][ext][query]'
					}
				},
			]
		},

		plugins: [
			// remove old dist files
			new CleanWebpackPlugin(),

			// Provides jQuery for other JS bundled with Webpack
			new webpack.ProvidePlugin({
				$: "jquery",
				jQuery: "jquery",
				Popper: ["popper.js", "default"],
			}),

			// some plugins with webpack include all locales which then is huge in size -> include only defined
			new webpack.ContextReplacementPlugin(/moment[\/\\]locale$/, SUPPORTED_LANGS),
			new webpack.ContextReplacementPlugin(/flatpickr[\/\\]dist[\/\\]l10n$/, SUPPORTED_LANGS),

			// extract css sheets to a separate file so we can include it directly in layout latte
			new MiniCssExtractPlugin({
				filename: 'css/[name].css',
				chunkFilename: 'css/[name].[hash:5].css',
			}),

		],

		resolve: {
			modules: ['node_modules'],
			alias: {
				JsComponents: path.resolve(__dirname, 'app')
			}
		}
	};

	if (isProduction) {
		config.plugins.push(
			// Generate statistics
			new BundleAnalyzerPlugin({
				analyzerMode: 'disabled',
				generateStatsFile: true,
				statsOptions: { source: false }
			}),
		);

		config.plugins.push(
			// Minify CSS
			new CssMinimizerWebpackPlugin()
		)
	}

	return config;
};
