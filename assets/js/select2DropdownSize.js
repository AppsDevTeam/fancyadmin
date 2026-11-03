// Seznam Select2 visí v <body> mimo pole, velikost (proměnné z _select2.scss) mu proto předáváme z pole
const SIZE_VARS = ['--select2-height', '--select2-padding-x'];

$(document).on('select2:open', (e) => {
	const select2 = $(e.target).data('select2');
	if (!select2) {
		return;
	}

	const field = getComputedStyle(select2.$container[0]);
	const dropdown = select2.$dropdown[0];
	SIZE_VARS.forEach((name) => dropdown.style.setProperty(name, field.getPropertyValue(name)));
});
