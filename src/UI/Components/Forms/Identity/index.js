const run = (el) => {
	$.nette.ext('live').after(function (el) {
		$(el).find('[name$="[account]"]').on('change', function (e) {
			$(el).find('[name="chosenCompany"]').click();
		});
	});
};

export default {run};