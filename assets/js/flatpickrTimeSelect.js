// Plugin flatpickru: k číselným polím hodin a minut přidává rozbalovací seznam,
// aby se čas dal vybrat stejně jako měsíc a rok. Pole zůstává editovatelné —
// čas mimo krok seznamu (7:13) se pořád dá napsat ručně.
//
// Použití: flatpickr(input, { enableTime: true, plugins: [timeSelect()] })
//          flatpickr(input, { plugins: [timeSelect({ minuteStep: 5 })] })
//
// Krok minut se bere z `minuteIncrement` instance (výchozí flatpickr = 5), takže
// stačí nastavit ho a seznam se přizpůsobí. Krok má odpovídat mřížce, ve které
// projekt plánuje — jinak jdou přes seznam vybrat časy, které v kalendáři neexistují.

import attachDropdown from './flatpickrDropdown';

const pad = (value) => String(value).padStart(2, '0');

export default function timeSelect(pluginConfig = {}) {
	return (fp) => {
		let hourSelect = null;
		let minuteSelect = null;

		const minuteStep = () => pluginConfig.minuteStep ?? fp.config.minuteIncrement ?? 5;

		const hourValues = () => {
			// Ve 12hodinovém režimu drží dopoledne/odpoledne samostatný přepínač
			const values = [];
			if (fp.config.time_24hr) {
				for (let h = 0; h <= 23; h++) { values.push(h); }
			} else {
				for (let h = 1; h <= 12; h++) { values.push(h); }
			}
			return values;
		};

		const minuteValues = () => {
			const step = minuteStep();
			const values = [];
			for (let m = 0; m < 60; m += step) { values.push(m); }

			// Uložená hodnota mimo krok (import, ruční zápis) musí v seznamu zůstat,
			// jinak by seznam ukazoval jiný čas, než jaký je ve formuláři.
			const current = parseInt(fp.minuteElement.value, 10);
			if (!Number.isNaN(current) && !values.includes(current)) {
				values.push(current);
				values.sort((a, b) => a - b);
			}
			return values;
		};

		const fill = (select, values) => {
			const rendered = values.join(',');
			if (select.dataset.values === rendered) {
				return;
			}
			select.dataset.values = rendered;
			select.textContent = '';
			values.forEach((value) => {
				const option = document.createElement('option');
				option.value = String(value);
				option.textContent = pad(value);
				select.appendChild(option);
			});
		};

		const sync = () => {
			if (!hourSelect) {
				return;
			}
			fill(hourSelect, hourValues());
			fill(minuteSelect, minuteValues());
			hourSelect.value = String(parseInt(fp.hourElement.value, 10) || 0);
			minuteSelect.value = String(parseInt(fp.minuteElement.value, 10) || 0);
		};

		// Čas nastavujeme přes veřejné setDate, ne přes vnitřní události flatpickru —
		// ten si tím sám přepočítá hodnotu inputu, limity i zobrazení.
		const apply = () => {
			let hours = parseInt(hourSelect.value, 10);
			const minutes = parseInt(minuteSelect.value, 10);

			if (!fp.config.time_24hr && fp.amPM) {
				hours = (hours % 12) + (fp.amPM.textContent === fp.l10n.amPM[1] ? 12 : 0);
			}

			const date = fp.selectedDates[0] ? new Date(fp.selectedDates[0]) : new Date();
			date.setHours(hours, minutes, 0, 0);
			fp.setDate(date, true);
		};

		const build = (field) => {
			const select = document.createElement('select');
			// select-default drží dál Select2 — ten by si select přebral a jeho dropdown
			// by se otvíral mimo kalendář (stejně jako u měsíce a roku).
			select.className = 'flatpickr-timeDropdown select-default';
			select.addEventListener('change', apply);
			field.classList.add('flatpickr-timeInput-replaced');
			field.appendChild(select);
			// Ze selectu je vidět jen šipka, nativní seznam by proto byl pár pixelů
			// široký — seznam kreslí vlastní panel zarovnaný na pravý okraj pole
			attachDropdown(select, fp.calendarContainer, { align: 'end', minWidth: 80 });
			return select;
		};

		return {
			onReady() {
				if (!fp.hourElement || !fp.minuteElement) {
					return;
				}

				hourSelect = build(fp.hourElement.parentNode);
				hourSelect.setAttribute('aria-label', fp.l10n.hourAriaLabel ?? 'Hour');

				minuteSelect = build(fp.minuteElement.parentNode);
				minuteSelect.setAttribute('aria-label', fp.l10n.minuteAriaLabel ?? 'Minute');

				sync();
			},

			onValueUpdate: sync,
			onChange: sync,
			onOpen: sync,

			onDestroy() {
				hourSelect?.remove();
				minuteSelect?.remove();
				hourSelect = minuteSelect = null;
			},
		};
	};
}
