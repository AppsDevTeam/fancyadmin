import $ from 'jquery';

const scheduleAutoClose = (root) => {
	// Zavírají se pouze zprávy s atributem data-close-duration (řídí se v PHP – viz
	// flashMessageCommon). Zprávy bez něj (warning/danger/info) zůstávají do zavření uživatelem.
	$(root).find('.alert[data-close-duration]').each(function () {
		const $alert = $(this);
		if ($alert.data('auto-close-scheduled')) {
			return;
		}
		const duration = parseInt($alert.attr('data-close-duration'), 10);
		if (!duration) {
			return;
		}
		$alert.data('auto-close-scheduled', true);
		setTimeout(() => $alert.fadeOut(200, () => $alert.remove()), duration);
	});
};

$(document).on('click', '.alert .alert-close-btn', function () {
	$(this).closest('.alert').remove();
});

$(document).ready(() => scheduleAutoClose(document));

if (window.$ && window.$.nette) {
	$.nette.ext('flashes', {
		success: function () {
			scheduleAutoClose(document);
		}
	});
}
