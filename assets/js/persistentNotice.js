import $ from 'jquery';

/**
 * Trvaly panel s platnosti (PersistentNotice::$expiresAt, v sablone `data-expires-at` jako unix cas
 * v ms) zmizi, kdyz platnost uplyne. Server ho jinak kresli jen pri vykresleni stranky, takze by na
 * otevrene strance zustal, dokud ji uzivatel neobnovi nebo neprejde jinam.
 *
 * Kontroluje se po nacteni stranky, po kazdem ajaxu (snippet panelu i cele body se mohly prekreslit)
 * a po navratu na kartu - casovac v neaktivni karte prohlizec zdrzuje.
 */
let timer = null;

const check = () => {
	clearTimeout(timer);
	timer = null;

	const notice = document.querySelector('.persistent-notice[data-expires-at]');
	const expiresAt = notice ? parseInt(notice.getAttribute('data-expires-at'), 10) : NaN;
	if (!notice || !expiresAt) {
		return;
	}

	const remaining = expiresAt - Date.now();
	if (remaining <= 0) {
		notice.remove();
		return;
	}

	// setTimeout uz delsi nez ~24,8 dne nepocka
	timer = setTimeout(check, Math.min(remaining, 2147483647));
};

$(document).ready(check);

document.addEventListener('visibilitychange', () => {
	if (!document.hidden) {
		check();
	}
});

if (window.$ && window.$.nette) {
	$.nette.ext('persistentNotice', {
		complete: check,
	});
}
