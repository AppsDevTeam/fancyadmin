$(function () {
	let isDirty = false;

	function closeSidePanel() {
		$('#snippet--sidePanel').html('');
		isDirty = false;
	}

	function tryClose() {
		const confirmMessage = $('#snippet--sidePanel .side-panel-config').data('close-confirm') || 'Close without saving?';
		if (isDirty && !confirm(confirmMessage)) return;
		closeSidePanel();
	}

	// Po AJAX odpovědi: reset dirty jen při úspěšném save
	// payload.hasErrors = true nastavuje BootstrapFormRenderer při validační chybě
	$.nette.ext('sidePanelDirty', {
		success: function (payload) {
			if (payload.hasErrors) return; // validace selhala → dirty zůstane
			if (payload.snippets && ('snippet--sidePanel' in payload.snippets)) {
				isDirty = false; // save OK nebo panel se otevřel → čisté
			}
		}
	});

	// Změna hodnoty v inputu → dirty
	$(document).on('change input', '#snippet--sidePanel form :input', () => {
		isDirty = true;
	});

	// Přidání / odebrání řádku replikátorem → dirty
	$(document).on('click', '#snippet--sidePanel [data-adt-replicator-add], #snippet--sidePanel [data-adt-replicator-remove]', () => {
		isDirty = true;
	});

	$(document).on('click', '.side-panel-template-backdrop', tryClose);
	$(document).on('click', '.side-panel-template-container .btn-close', tryClose);
});