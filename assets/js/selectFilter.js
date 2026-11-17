// Filtr (.select-filter) - šířka pole podle nejdelší volby jako u nativního selectu, aby se při
// přepínání neměnila. Text se měří přes canvas: filtr bývá ve skryté záložce, kde nic nemá rozměr.
import { observe } from 'adt-js-components';

let canvas;

function context() {
	canvas ??= document.createElement('canvas').getContext('2d');
	return canvas;
}

export function fit(select) {
	const ctx = context();
	if (!ctx) {
		return;
	}

	// Původní select (Tom Select ho jen skryje) má písmo svého stupně stejně jako jeho pole
	const style = getComputedStyle(select);
	ctx.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
	const width = Math.max(0, ...[...select.options].map((option) => ctx.measureText(option.text).width));
	select.closest('.select-filter').style.setProperty('--select-filter-text-width', Math.ceil(width) + 'px');
}

export function initSelectFilters() {
	document.querySelectorAll('.select-filter > select').forEach(fit);
}

export const stopObserving = observe('.select-filter > select', fit);

// Písmo se načítá až po prvním změření, s ním se šířky změní
document.fonts?.ready.then(initSelectFilters);
