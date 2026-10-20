// Datum a čas — flatpickr (https://github.com/flatpickr/flatpickr) místo nativních
// inputů, jejichž formát se řídí jazykem prohlížeče (anglický = mm/dd/rrrr, AM/PM).
//
// Projektu stačí jediný import; vzhled kalendáře si přitáhne `_flatpickr.scss`
// (součást app.scss), takže se nikde nic dalšího nezapojuje:
//
//   import { initDateInput } from '@fancyadmin/js/dateInput';
//   initDateInput({ locale: Czech, minuteIncrement: 15 });
//
// Picker se nasadí sám na každý nativní date / time / datetime-local input (ty
// vyrábí Nette addDate/addTime/addDateTime), takže běžný formulář nemusí nastavovat
// nic — formát hodnoty i meze se odvodí z typu a atributů min/max. Opt-out je třída
// date-default (stejná konvence jako select-default u Select2). Atribut
// data-flatpickr (JSON) odvozené nastavení přebíjí a zapne picker i na obyčejném
// text inputu. Volá se na DOM ready i po AJAX překreslení; už inicializované inputy
// mají třídu flatpickr-input a přeskakují se.

import flatpickr from 'flatpickr';

import attachDropdown from './flatpickrDropdown';
import yearSelect from './flatpickrYearSelect';
import timeSelect from './flatpickrTimeSelect';

const SELECTOR = 'input[type="date"], input[type="time"], input[type="datetime-local"], input[data-flatpickr]';

// Instance kvůli úklidu: kalendář visí v <body>, takže po AJAX překreslení snippetu
// by po odstraněném inputu zůstal viset osiřelý kalendář.
const instances = [];

function cleanup() {
	for (let i = instances.length - 1; i >= 0; i--) {
		if (!instances[i].input.isConnected) {
			instances[i].destroy();
			instances.splice(i, 1);
		}
	}
}

// Nette posílá vteřiny jen u withSeconds; bez detekce by je picker uřízl a odeslaná
// hodnota by se lišila od té načtené.
function hasSeconds(input) {
	return [input.value, input.min, input.max].some((value) => /\d{2}:\d{2}:\d{2}/.test(value || ''));
}

// Nastavení odvozené z typu inputu. dateFormat musí odpovídat formátu, ve kterém
// hodnotu čeká Nette DateTimeControl (ten ji v inputu i vykresluje); altFormat je jen
// zobrazení pro uživatele, původní input zůstává skrytý s hodnotou pro server.
function typeDefaults(input) {
	const seconds = hasSeconds(input);

	switch (input.type) {
		case 'date':
			return { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true };

		case 'time':
			return {
				dateFormat: seconds ? 'H:i:S' : 'H:i',
				enableTime: true,
				enableSeconds: seconds,
				noCalendar: true,
				time_24hr: true,
				allowInput: true,
			};

		case 'datetime-local':
			return {
				dateFormat: seconds ? 'Y-m-d\\TH:i:S' : 'Y-m-d\\TH:i',
				enableTime: true,
				enableSeconds: seconds,
				time_24hr: true,
				altInput: true,
				altFormat: seconds ? 'd.m.Y H:i:S' : 'd.m.Y H:i',
				allowInput: true,
			};

		default:
			return {};
	}
}

// Atributy min/max vyrábí Nette z pravidla RANGE. Pro flatpickr je to jiná dvojice
// klíčů — a u data navíc rozsah, ze kterého plugin skládá seznam roků.
function rangeOptions(input) {
	const range = {};

	if (input.type === 'time') {
		if (input.min) range.minTime = input.min;
		if (input.max) range.maxTime = input.max;
	} else {
		if (input.min) range.minDate = input.min;
		if (input.max) range.maxDate = input.max;
	}

	return range;
}

export function initDateInput(defaults = {}) {
	cleanup();

	document.querySelectorAll(SELECTOR).forEach((input) => {
		if (input.classList.contains('flatpickr-input') || input.classList.contains('date-default')) {
			return;
		}

		const derived = { ...typeDefaults(input), ...rangeOptions(input) };
		const options = JSON.parse(input.dataset.flatpickr || '{}');

		// type=text, aby prohlížeč nenabízel vlastní nativní picker vedle flatpickru
		input.type = 'text';

		const instance = flatpickr(input, {
			// Rok, hodiny a minuty jsou v kalendáři rozbalovací seznamy; flatpickr je
			// renderuje jako číselná pole se šipkami, která se špatně trefují.
			plugins: [yearSelect(), timeSelect()],
			...defaults,
			// Formát drátové hodnoty diktuje server, ne projekt — proto až za defaults.
			...derived,
			...options,
		});

		// Interní select měsíce nesmí dostat globální Select2 — jeho dropdown by se
		// otvíral mimo kalendář; třídu přidáváme synchronně, MutationObserver Select2
		// běží až po dokončení initu.
		instance.monthsDropdownContainer?.classList.add('select-default');

		// Seznam měsíců kreslí stejný panel jako rok a čas (pluginy si ho napojují
		// samy), jinak by jako jediný zůstal nativní
		if (instance.monthsDropdownContainer) {
			attachDropdown(instance.monthsDropdownContainer, instance.calendarContainer, { minWidth: 150 });
		}

		instances.push(instance);
	});
}

export default { initDateInput };
