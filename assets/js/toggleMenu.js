$.nette.ext('live').after(function (el, data) {

	setTimeout(() => {
		$(el).find('.delay-animate').removeClass('delay-animate');
	}, 250)

	$(el).find('.bottom-mobile-menu a').on('click', (e) => {
		e.stopPropagation();
		const selector = $(e.currentTarget).attr('href');
		const hasSubMenu = selector.startsWith('#') || selector.startsWith('.');
		const currentIsOpened = $(e.currentTarget).hasClass('isOpened');

		$('.bottom-mobile-menu a').map((i, el) => {
			if ($(el).attr('href').startsWith('#') || $(el).attr('href').startsWith('.')) {
				$($(el).attr('href')).toggleClass('opened', false);
			}
			$(el).toggleClass('isOpened', false);
		});

		if (hasSubMenu && !currentIsOpened) {
			const $subMenu = $(selector);
			const isOpened = $subMenu.hasClass('opened');

			$subMenu.toggleClass('opened', !isOpened);
			$(e.currentTarget).toggleClass('isOpened', !isOpened);
		}

	})

	$(el).find('#snippet--container').map((i, el) => {
		let prevScrollPos = $(el).scrollTop();
		let top = 0;
		$(el).on('scroll', (e) => {
			if ($(window).width() < 575) {
				const innerHeight = $(el)[0].scrollHeight;
				const outerHeight = $(el).height();
				let currentScrollPos = Math.min(Math.max($(el).scrollTop(), 0), (innerHeight - outerHeight - 80));
				let diff = currentScrollPos - prevScrollPos;

				if ((innerHeight - outerHeight) > 15 && Math.abs(diff) > 0) {
					top = Math.min(Math.max(top + diff, 0), 80);
					prevScrollPos = currentScrollPos;
				}
			} else {
				prevScrollPos = $(el).scrollTop();
				top = 0;
			}

			$('.navbar').css({top: `${0 - top}px`});
		});
	})
});