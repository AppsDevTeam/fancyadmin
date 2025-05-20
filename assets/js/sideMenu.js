$.nette.ext('live').after(function (el) {
	$(el).find('.menu').menuAim({
		rowSelector: "> .item",
		submenuSelector: '.submenu',
		activate: (el) => {
			$(el).addClass('opened');
		},
		deactivate: (el) => {
			$(el).removeClass('opened');
		},
		exitMenu: () => {
			$(el).find('.menu > .item').removeClass('opened');
			return true;
		}
	});
});
