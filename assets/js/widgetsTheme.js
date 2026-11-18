// Plugin Chart.js `fancyadminTheme` (docs/widgets.md): barvy datasetů, mřížky, os, legendy a tooltipu z CSS
// proměnných fancyadminu, takže graf sedí ve světlém i tmavém režimu. Barvu zadanou v konfiguraci nepřepíše;
// na `fancyadmin:theme-change` barvy přepočítá a grafy překreslí. Nenačítá chart.js, jen pracuje s instancemi.

// Druhý dataset (typicky předchozí období) je tlumený, aby aktuální období vyniklo
export const PALETTE = ['--primaryColor', '--fa-text-muted', '--dashboardAccentColor', '--bs-info', '--bs-success', '--bs-warning'];

const NO_AXES = ['doughnut', 'pie', 'polarArea', 'radar'];

const charts = new Set();
// Hodnoty, které nastavil plugin: ty smí přepsat (změna tématu), cizí ne
const applied = new WeakMap();

export function cssVar(name, root = document.documentElement) {
	return getComputedStyle(root).getPropertyValue(name).trim();
}

/** Barva s průhledností pro výplň; neznámý zápis vrátí beze změny. */
export function withAlpha(color, alpha) {
	let hex = /^#([0-9a-f]{3,8})$/i.exec(color)?.[1];
	if (hex && (hex.length === 3 || hex.length === 6 || hex.length === 8)) {
		if (hex.length === 3) {
			hex = [...hex].map((c) => c + c).join('');
		}
		const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
		return `rgba(${r}, ${g}, ${b}, ${alpha})`;
	}
	const rgb = /^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/i.exec(color);
	return rgb ? `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alpha})` : color;
}

function paletteColor(index) {
	const color = cssVar(PALETTE[index % PALETTE.length]);
	return color || cssVar('--primaryColor');
}

/** Nastaví hodnotu, jen když chybí nebo ji nastavil plugin; vrací, jestli se něco změnilo. */
function setDefault(chart, object, key, value, path) {
	const ours = applied.get(chart);
	if (object[key] !== undefined && object[key] !== ours.get(path)) {
		return false;
	}
	ours.set(path, value);
	if (object[key] === value) {
		return false;
	}
	object[key] = value;
	return true;
}

const child = (object, key) => (object[key] ??= {});

/** Doplní barvy do konfigurace grafu; vrací, jestli se změnila nějaká volba (ne dataset). */
export function applyTheme(chart) {
	if (!applied.has(chart)) {
		applied.set(chart, new Map());
	}
	const type = chart.config.type;
	const options = chart.config.options ??= {};
	const text = cssVar('--fa-text');
	const muted = cssVar('--fa-text-muted');
	const line = cssVar('--fa-line') || cssVar('--fa-border');

	(chart.data.datasets ?? []).forEach((dataset, index) => {
		const path = `datasets.${index}.`;
		if (NO_AXES.includes(dataset.type ?? type)) {
			const colors = (dataset.data ?? []).map((_, i) => paletteColor(i));
			setDefault(chart, dataset, 'backgroundColor', colors, path + 'backgroundColor');
			setDefault(chart, dataset, 'borderColor', cssVar('--fa-surface'), path + 'borderColor');
			return;
		}
		const color = paletteColor(index);
		const isLine = (dataset.type ?? type) === 'line';
		setDefault(chart, dataset, 'borderColor', color, path + 'borderColor');
		setDefault(chart, dataset, 'backgroundColor', isLine ? withAlpha(color, 0.12) : withAlpha(color, 0.85), path + 'backgroundColor');
		if (isLine) {
			setDefault(chart, dataset, 'pointBackgroundColor', color, path + 'pointBackgroundColor');
		}
	});

	let changed = false;
	if (!NO_AXES.includes(type)) {
		const scales = child(options, 'scales');
		for (const axis of ['x', 'y']) {
			const scale = child(scales, axis);
			changed = setDefault(chart, child(scale, 'grid'), 'color', line, `scales.${axis}.grid.color`) || changed;
			changed = setDefault(chart, child(scale, 'border'), 'color', line, `scales.${axis}.border.color`) || changed;
			changed = setDefault(chart, child(scale, 'ticks'), 'color', muted, `scales.${axis}.ticks.color`) || changed;
		}
	}
	const plugins = child(options, 'plugins');
	changed = setDefault(chart, child(child(plugins, 'legend'), 'labels'), 'color', text, 'legend.labels.color') || changed;
	const tooltip = child(plugins, 'tooltip');
	changed = setDefault(chart, tooltip, 'backgroundColor', cssVar('--fa-surface'), 'tooltip.backgroundColor') || changed;
	changed = setDefault(chart, tooltip, 'titleColor', text, 'tooltip.titleColor') || changed;
	changed = setDefault(chart, tooltip, 'bodyColor', text, 'tooltip.bodyColor') || changed;
	changed = setDefault(chart, tooltip, 'borderColor', cssVar('--fa-border'), 'tooltip.borderColor') || changed;
	changed = setDefault(chart, tooltip, 'borderWidth', 1, 'tooltip.borderWidth') || changed;
	return changed;
}

// Chart.js si volby os vyhodnotí ještě před beforeUpdate; když je plugin doplnil až tam (builtin při změně
// atributu vymění celé volby), je potřeba druhý průchod, jinak by osy zůstaly v barvách Chart.js.
const needsSecondPass = new WeakSet();

export const fancyadminTheme = {
	id: 'fancyadminTheme',
	beforeInit(chart) {
		charts.add(chart);
		applyTheme(chart);
	},
	beforeUpdate(chart) {
		if (applyTheme(chart)) {
			needsSecondPass.add(chart);
		}
	},
	afterUpdate(chart) {
		if (needsSecondPass.has(chart)) {
			needsSecondPass.delete(chart);
			chart.update('none');
		}
	},
	afterDestroy(chart) {
		charts.delete(chart);
	},
};

/** Grafy s pluginem, které jsou na stránce (pro testy a překreslení po změně tématu). */
export function themedCharts() {
	return [...charts];
}

document.addEventListener('fancyadmin:theme-change', () => {
	for (const chart of charts) {
		applyTheme(chart);
		chart.update('none');
	}
});
