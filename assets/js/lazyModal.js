// Modal s obsahem načítaným až při prvním otevření (docs/lazy-modal.md). data-lazy-url je signál
// lazyComponent! (LazyComponentsTrait), který překreslí snippet těla modalu - stránka se tak
// nezdržuje sestavováním formulářů, které uživatel většinou neotevře. Po načtení dostane modal
// data-lazy-state="loaded" a událost lazy-modal:loaded; kód, který formulář v modalu předvyplňuje,
// na ni počká přes whenLazyModalLoaded().

export function whenLazyModalLoaded(modal, callback) {
	if (!modal.dataset.lazyUrl || modal.dataset.lazyState === 'loaded') {
		callback();
		return;
	}
	$(modal).one('lazy-modal:loaded', callback);
}

$(document).on('show.bs.modal', '.modal[data-lazy-url]', function () {
	const modal = this;
	if (modal.dataset.lazyState) {
		return;
	}
	modal.dataset.lazyState = 'loading';
	$.nette.ajax({ url: modal.dataset.lazyUrl, off: ['history'] })
		.done(() => {
			modal.dataset.lazyState = 'loaded';
			$(modal).trigger('lazy-modal:loaded');
		})
		.fail(() => {
			// Další otevření to zkusí znovu
			delete modal.dataset.lazyState;
		});
});
