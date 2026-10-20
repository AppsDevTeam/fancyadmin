// Plugin flatpickru: rok v hlavičce kalendáře jako rozbalovací seznam, aby vypadal
// a ovládal se stejně jako měsíc. Flatpickr sám renderuje rok jako číselný input.
//
// Použití: flatpickr(input, { plugins: [yearSelect()] })
//          flatpickr(input, { plugins: [yearSelect({ past: 100, future: 0 })] })
//
// Rozsah let: primárně z minDate/maxDate instance. Když nejsou, bere se okolí
// aktuálního roku (výchozí ±10) — pro pole typu „datum narození" je proto potřeba
// buď nastavit minDate, nebo rozsah předat v konfiguraci pluginu.

import attachDropdown from './flatpickrDropdown';

export default function yearSelect(pluginConfig = {}) {
	const past = pluginConfig.past ?? 10;
	const future = pluginConfig.future ?? 10;

	return (fp) => {
		let select = null;
		let renderedRange = null;

		const getRange = () => {
			const now = new Date().getFullYear();
			const min = fp.config.minDate ? fp.config.minDate.getFullYear() : now - past;
			const max = fp.config.maxDate ? fp.config.maxDate.getFullYear() : now + future;

			// Zobrazený rok musí být v seznamu i když leží mimo rozsah (otevření
			// starého záznamu), jinak by select ukazoval něco jiného než kalendář.
			return [Math.min(min, fp.currentYear), Math.max(max, fp.currentYear)];
		};

		const render = () => {
			if (!select) {
				return;
			}

			const [min, max] = getRange();

			// Přestavovat seznam jen při změně rozsahu — překreslení při každém
			// prolistování měsíce by zahazovalo otevřený dropdown.
			if (!renderedRange || renderedRange[0] !== min || renderedRange[1] !== max) {
				renderedRange = [min, max];
				select.textContent = '';
				for (let year = max; year >= min; year--) {
					const option = document.createElement('option');
					option.value = String(year);
					option.textContent = String(year);
					select.appendChild(option);
				}
			}

			select.value = String(fp.currentYear);
		};

		return {
			onReady() {
				const wrapper = fp.currentYearElement?.parentNode;
				if (!wrapper) {
					return;
				}

				// Původní input zůstává v DOM, jen schovaný: flatpickr do něj dál
				// zapisuje aktuální rok a počítá s jeho existencí.
				wrapper.classList.add('flatpickr-yearInput-replaced');

				select = document.createElement('select');
				// select-default drží dál Select2 — ten by si select přebral a jeho
				// dropdown by se otvíral mimo kalendář (stejně jako u měsíce).
				select.className = 'flatpickr-yearDropdown-years select-default';
				select.setAttribute('aria-label', fp.l10n.yearAriaLabel ?? 'Year');
				select.addEventListener('change', (event) => {
					fp.changeYear(parseInt(event.target.value, 10));
				});

				wrapper.parentNode.insertBefore(select, wrapper.nextSibling);
				attachDropdown(select, fp.calendarContainer, { minWidth: 110 });
				render();
			},

			onYearChange: render,
			onMonthChange: render,
			onValueUpdate: render,
			onOpen: render,

			onDestroy() {
				select?.remove();
				select = null;
				renderedRange = null;
			},
		};
	};
}
