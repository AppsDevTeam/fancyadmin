// Seznam Tom Selectu může viset mimo pole (dropdownParent: 'body' v panelu nebo tabulce s overflow)
// a pak nedědí jeho rozměry - proměnné škály z _tomSelect.scss mu při otevření předáváme z pole.
import { observe } from 'adt-js-components';

export const SIZE_VARS = ['--control-height', '--control-padding-x', '--control-font'];

export function copyFieldSize(wrapper, dropdown) {
	const field = getComputedStyle(wrapper);
	SIZE_VARS.forEach((name) => {
		const value = field.getPropertyValue(name).trim();
		if (value) {
			dropdown.style.setProperty(name, value);
		}
	});
}

export const stopObserving = observe('.ts-wrapper', (wrapper) => {
	const tomSelect = wrapper.previousElementSibling?.tomselect;
	if (!tomSelect) {
		return;
	}

	tomSelect.on('dropdown_open', (dropdown) => copyFieldSize(wrapper, dropdown));
});
