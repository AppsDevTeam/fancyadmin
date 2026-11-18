// Plugin fancyadminTheme (assets/js/widgetsTheme.js) a aktualizace grafu ve widgetu (assets/js/widgets.js).
// Chart.js je nahrazený objektem s config, data a update() - plugin s instancí víc nedělá.
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let theme;
let chartUpdateOperation;
let chartPlugins;

const VARS = {
	'--primaryColor': '#ff0000',
	'--fa-text-muted': 'rgb(100, 110, 120)',
	'--dashboardAccentColor': '#00f',
	'--fa-text': '#111111',
	'--fa-line': '#dddddd',
	'--fa-border': '#cccccc',
	'--fa-surface': '#ffffff',
};

function setVars(vars) {
	for (const [name, value] of Object.entries(vars)) {
		document.documentElement.style.setProperty(name, value);
	}
}

function fakeChart(type = 'line', datasets = [{ label: 'Tržba', data: [1, 2] }, { label: 'Předchozí', data: [3, 4] }], options = {}) {
	return { config: { type, options }, data: { datasets }, update: vi.fn() };
}

/** Vytvoření grafu tak, jak ho volá Chart.js: beforeInit, pak první update. */
function init(chart) {
	theme.fancyadminTheme.beforeInit(chart);
	theme.fancyadminTheme.beforeUpdate(chart);
	theme.fancyadminTheme.afterUpdate(chart);
	return chart;
}

beforeAll(async () => {
	await import('../../assets/js/_jquery-global.js');
	await import('../../assets/js/naja.js');
	theme = await import('../../assets/js/widgetsTheme.js');
	({ chartUpdateOperation } = await import('../../assets/js/widgets.js'));
	({ chartPlugins } = await import('adt-js-components/src/Chart/plugins'));
});

beforeEach(() => {
	setVars(VARS);
	for (const chart of theme.themedCharts()) {
		theme.fancyadminTheme.afterDestroy(chart);
	}
});

describe('barvy z CSS proměnných', () => {
	it('datasety dostanou paletu: první primární, druhý tlumený, výplň čáry průhledná', () => {
		const chart = init(fakeChart());
		const [current, previous] = chart.data.datasets;

		expect(current.borderColor).toBe('#ff0000');
		expect(current.backgroundColor).toBe('rgba(255, 0, 0, 0.12)');
		expect(current.pointBackgroundColor).toBe('#ff0000');
		expect(previous.borderColor).toBe('rgb(100, 110, 120)');
	});

	it('osy, legenda a tooltip dostanou barvy tématu, sloupce plnou výplň', () => {
		const chart = init(fakeChart('bar', [{ label: 'Kusy', data: [5] }]));
		const { scales, plugins } = chart.config.options;

		expect(chart.data.datasets[0].backgroundColor).toBe('rgba(255, 0, 0, 0.85)');
		expect(scales.x.grid.color).toBe('#dddddd');
		expect(scales.y.border.color).toBe('#dddddd');
		expect(scales.y.ticks.color).toBe('rgb(100, 110, 120)');
		expect(plugins.legend.labels.color).toBe('#111111');
		expect(plugins.tooltip.backgroundColor).toBe('#ffffff');
		expect(plugins.tooltip.borderColor).toBe('#cccccc');
	});

	it('koláč nemá osy a každý díl má svou barvu', () => {
		const chart = init(fakeChart('doughnut', [{ data: [1, 2, 3] }]));

		expect(chart.config.options.scales).toBeUndefined();
		expect(chart.data.datasets[0].backgroundColor).toEqual(['#ff0000', 'rgb(100, 110, 120)', '#00f']);
	});

	it('barvy zadané v konfiguraci zůstanou', () => {
		const chart = init(fakeChart('line', [{ data: [1], borderColor: 'green' }], { scales: { x: { grid: { color: 'pink' } } } }));

		expect(chart.data.datasets[0].borderColor).toBe('green');
		expect(chart.config.options.scales.x.grid.color).toBe('pink');
		expect(chart.config.options.scales.y.grid.color).toBe('#dddddd');
	});
});

describe('změna tématu a aktualizace', () => {
	it('fancyadmin:theme-change přepočítá barvy pluginu, cizí nechá, a graf překreslí bez animace', () => {
		const chart = init(fakeChart('line', [{ data: [1] }, { data: [2], borderColor: 'green' }]));
		chart.update.mockClear();

		setVars({ '--primaryColor': '#00ff00', '--fa-line': '#333333' });
		document.dispatchEvent(new CustomEvent('fancyadmin:theme-change', { detail: { theme: 'dark' } }));

		expect(chart.data.datasets[0].borderColor).toBe('#00ff00');
		expect(chart.data.datasets[1].borderColor).toBe('green');
		expect(chart.config.options.scales.x.grid.color).toBe('#333333');
		expect(chart.update).toHaveBeenCalledWith('none');
	});

	it('zničený graf se po změně tématu nepřekresluje', () => {
		const chart = init(fakeChart());
		theme.fancyadminTheme.afterDestroy(chart);
		chart.update.mockClear();

		document.dispatchEvent(new CustomEvent('fancyadmin:theme-change'));

		expect(chart.update).not.toHaveBeenCalled();
	});

	it('vyměněné volby (nová data z widgetu) doplní a graf jednou projde znovu, aby je osy převzaly', () => {
		const chart = init(fakeChart());
		chart.update.mockClear();

		chart.config.options = { maintainAspectRatio: false };
		chart.data = { datasets: [{ data: [9] }] };
		theme.fancyadminTheme.beforeUpdate(chart);
		theme.fancyadminTheme.afterUpdate(chart);

		expect(chart.config.options.scales.x.grid.color).toBe('#dddddd');
		expect(chart.data.datasets[0].borderColor).toBe('#ff0000');
		expect(chart.update).toHaveBeenCalledTimes(1);

		theme.fancyadminTheme.beforeUpdate(chart);
		theme.fancyadminTheme.afterUpdate(chart);
		expect(chart.update).toHaveBeenCalledTimes(1);
	});
});

describe('pomocné funkce a registrace', () => {
	it('withAlpha převede hex a rgb, neznámý zápis nechá', () => {
		expect(theme.withAlpha('#abc', 0.5)).toBe('rgba(170, 187, 204, 0.5)');
		expect(theme.withAlpha('#aabbccdd', 0.5)).toBe('rgba(170, 187, 204, 0.5)');
		expect(theme.withAlpha('rgb(1, 2, 3)', 0.2)).toBe('rgba(1, 2, 3, 0.2)');
		expect(theme.withAlpha('rgba(1 2 3 / 50%)', 0.2)).toBe('rgba(1, 2, 3, 0.2)');
		expect(theme.withAlpha('red', 0.2)).toBe('red');
	});

	it('widgets.js plugin zaregistruje do builtinu Chart', () => {
		expect(chartPlugins.fancyadminTheme).toBe(theme.fancyadminTheme);
	});
});

describe('graf v překresleném widgetu', () => {
	function widgetSnippet(inner) {
		document.body.innerHTML = `<section data-widget="sales"><div id="snippet-dashboard-sales-body">${inner}</div></section>`;
		return document.getElementById('snippet-dashboard-sales-body');
	}

	it('nový graf jen přepíše data-adt-chart stávajícího canvasu', () => {
		const snippet = widgetSnippet('<div class="fa-widgets-chart"><canvas data-adt-chart=\'{"type":"line","v":1}\'></canvas></div>');
		const canvas = snippet.querySelector('canvas');

		const operation = chartUpdateOperation(snippet, '<div class="fa-widgets-chart"><canvas data-adt-chart=\'{"type":"line","v":2}\'></canvas></div>');
		operation(snippet, '');

		expect(snippet.querySelector('canvas')).toBe(canvas);
		expect(JSON.parse(canvas.getAttribute('data-adt-chart')).v).toBe(2);
	});

	it('prázdná data, widget bez grafu a snippet mimo dashboard jdou běžnou výměnou', () => {
		const snippet = widgetSnippet('<canvas data-adt-chart="{}"></canvas>');
		expect(chartUpdateOperation(snippet, '<p class="fa-widgets-empty">Žádná data</p>')).toBeNull();

		const table = widgetSnippet('<table></table>');
		expect(chartUpdateOperation(table, '<canvas data-adt-chart="{}"></canvas>')).toBeNull();

		document.body.innerHTML = '<div id="snippet--other"><canvas data-adt-chart="{}"></canvas></div>';
		expect(chartUpdateOperation(document.getElementById('snippet--other'), '<canvas data-adt-chart="{}"></canvas>')).toBeNull();
	});
});
