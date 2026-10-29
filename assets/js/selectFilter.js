// Filtr (.select-filter) — šířka Select2 podle nejdelší volby jako u nativního selectu,
// aby se při přepínání neměnila. Text se měří přes canvas: filtr bývá ve skryté záložce.

const canvas = document.createElement('canvas').getContext('2d');

function fit(select) {
	const wrap = select.closest('.select-filter');
	// Původní select má písmo svého stupně stejně jako jeho Select2 (_select2.scss)
	const style = getComputedStyle(select);
	canvas.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
	const width = Math.max(...[...select.options].map((option) => canvas.measureText(option.text).width));
	wrap.style.setProperty('--select-filter-text-width', Math.ceil(width) + 'px');
}

function initSelectFilters() {
	document.querySelectorAll('.select-filter > select:not([data-select-filter-ready])').forEach((select) => {
		select.dataset.selectFilterReady = '1';
		fit(select);
	});
}

document.fonts?.ready.then(() => document.querySelectorAll('.select-filter > select').forEach(fit));

$(() => initSelectFilters());
$.nette.ext('live').after(() => initSelectFilters());

export { initSelectFilters };
