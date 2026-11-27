// Kurzor do prvního pole a TAB uvnitř bočního panelu nebo modalu (README, Fokus na první pole)

const FIELDS = 'input:not([type="hidden"], [type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="submit"], [type="button"], [type="reset"], [type="image"]), select, textarea';

// Na dotykovém zařízení by fokus vysunul klávesnici přes půl formuláře
const finePointer = window.matchMedia('(pointer: fine)');

function isFocusable(el) {
	if (el.disabled || el.readOnly || el.closest('.ts-wrapper')) return false;
	const box = el.tomselect ? el.tomselect.wrapper : el;
	return box.getClientRects().length > 0 && getComputedStyle(box).visibility !== 'hidden';
}

export function focusFirstField(root) {
	const active = document.activeElement;
	if (!finePointer.matches || (active !== root && root.contains(active) && active.matches('input, select, textarea, [contenteditable]'))) return;

	const fields = [...root.querySelectorAll(FIELDS)].filter(isFocusable);
	const field = fields.find((el) => el.hasAttribute('autofocus')) || fields.find((el) => el.classList.contains('is-invalid')) || fields[0];
	if (!field) return;

	// Seznam Tom Selectu se nerozbalí, pole jen dostane kurzor; jeho onFocus běží až v dalším tasku
	if (field.tomselect) {
		const ts = field.tomselect;
		const openOnFocus = ts.settings.openOnFocus;
		ts.settings.openOnFocus = false;
		ts.focus();
		setTimeout(() => { ts.settings.openOnFocus = openOnFocus; });
		return;
	}

	field.focus({ preventScroll: true });
	// Flatpickr se otevírá na focus, kalendář má vyskočit až na klik
	const picker = [...root.querySelectorAll('input')].map((input) => input._flatpickr).find((fp) => fp && (fp.altInput || fp.input) === field);
	if (picker) picker.close();
}

// Esc v poli jen vezme fokus (na kontejner, ať další Esc i TAB zůstanou uvnitř), zavírá až další Esc
export function leaveFieldOnEscape(e, container) {
	const field = e.target;
	if (e.key !== 'Escape' || e.defaultPrevented || !(field instanceof Element) || field === container || !container.contains(field)) return false;
	if (!field.matches('input, select, textarea') && !field.isContentEditable) return false;
	e.preventDefault();
	container.focus({ preventScroll: true });
	return true;
}

// TAB z posledního prvku na první (Shift+TAB obráceně), aby fokus nevyšel z panelu nebo modalu
export function keepTabInside(e, container) {
	if (e.key !== 'Tab') return;
	const items = [...container.querySelectorAll('a[href], button, input, select, textarea, [tabindex], [contenteditable="true"]')]
		.filter((el) => el.tabIndex >= 0 && !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
	if (items.length && e.target === (e.shiftKey ? items[0] : items[items.length - 1])) {
		e.preventDefault();
		(e.shiftKey ? items[items.length - 1] : items[0]).focus();
	}
}
