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

/**
 * Zobrazí flash zprávu z JS se stejným markupem, jaký renderuje layout.
 * Popisek zavíracího tlačítka bere z data-close-label na .snippet-flashes.
 * Stejnou zprávu, která už je zobrazená, nepřidává znovu.
 *
 * @param {string} message
 * @param {string} type success | info | warning | danger
 * @param {?number} closeDuration ms do automatického zavření, null = zůstane do zavření uživatelem
 */
export const showFlash = (message, type = 'danger', closeDuration = null) => {
	const $container = $('.snippet-flashes').first();
	if (!$container.length || !message) {
		return;
	}

	const alreadyShown = $container.find('.alert-' + type + ' .alert-text').filter(function () {
		return $(this).text() === message;
	}).length;
	if (alreadyShown) {
		return;
	}

	const $flash = $(
		'<div class="alert">' +
			'<div class="alert-text d-block"></div>' +
			'<div class="alert-close-btn-wrapper"><button class="alert-close-btn"></button></div>' +
		'</div>'
	);
	$flash.addClass('alert-' + type);
	$flash.find('.alert-text').text(message);
	$flash.find('.alert-close-btn').text($container.data('close-label') || '×');
	if (closeDuration) {
		$flash.attr('data-close-duration', closeDuration);
	}
	$container.append($flash);
	scheduleAutoClose($container);
};

/**
 * Hláška pro nezdařené uložení pořadí řádků gridu (přetažení myší).
 */
export const showSortableError = () => {
	showFlash($('.snippet-flashes').first().data('sortable-error'));
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
