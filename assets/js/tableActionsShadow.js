
import clamp from 'clamp-js';

$.nette.ext('live').after(function (el) {
	$('.table-responsive').map((i, el) => {
		$(el).on('scroll', (e) => {
			const scrollEl = $(e.currentTarget).get(0);
			const scrollWidth = scrollEl.scrollWidth;
			const clientWidth = scrollEl.clientWidth;
			const scrollLeft = scrollEl.scrollLeft;

			const fromRight = scrollWidth - clientWidth - scrollLeft;
			const showRightShadow = fromRight > 1;
			const showLeftShadow = scrollLeft > 1;

			$(el).find('> table, > .datagrid-tree').css({
				'--actionShadowWidthRight': showRightShadow ? '30px' : '0px',
				'--actionShadowWidthLeft': showLeftShadow ? '30px' : '0px'
			});
		}).trigger('scroll');
	});

	$(window).on('resize', () => {
		$('.table-responsive tr > td:first-child > div').map((i, el) => {
			clamp(el, {
				clamp: 2,
			});
		});
	}).trigger('resize');

	$(el).find('.table-responsive-wrapper').map((i, _el) => {
		const $tableOuter = $(_el);
		$(window).on('resize', () => {
			//$tableOuter.find('.table-bottom-scroll').css({width: $tableOuter.width()});
			$tableOuter.find('.table-bottom-scroll-inner').css({width: $tableOuter.find('table, .datagrid-tree').first().width() - 20});
		}).trigger('resize');

		$tableOuter.find('.table-responsive').on('scroll', function() {
			$tableOuter.find('.table-bottom-scroll').scrollLeft($(this).scrollLeft());
		});

		$tableOuter.find('.table-bottom-scroll').on('scroll', function() {
			$tableOuter.find('.table-responsive').scrollLeft($(this).scrollLeft());
		});
	})
});
