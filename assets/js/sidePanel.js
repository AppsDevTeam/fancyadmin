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

	function isPanelForm(settings) {
		return !!(settings && settings.nette && settings.nette.form
			&& settings.nette.form.closest
			&& settings.nette.form.closest('#snippet--sidePanel').length);
	}

	// Reset dirty řešíme už v "before" fázi odeslání formuláře z panelu.
	// Důvod: po úspěchu může jiná extension (submitForm) ve své "success"
	// fázi panel rovnou zavřít (např. po stažení souboru), a to dřív, než by
	// se sem stihl dostat "success" – reset by tak přišel pozdě a vyskočil by
	// potvrzovací dialog "Opravdu zavřít bez uložení?".
	$.nette.ext('sidePanelDirty', {
		before: function (xhr, settings) {
			// Optimisticky bereme odeslání panel formuláře jako "uloženo".
			if (isPanelForm(settings)) {
				isDirty = false;
			}
		},
		success: function (payload) {
			// Validace na serveru selhala → formulář zůstává rozdělaný.
			if (payload && payload.hasErrors) {
				isDirty = true;
				return;
			}
			// Otevření panelu / standardní uložení s překreslením snippetu → čisté.
			if (payload && payload.snippets && ('snippet--sidePanel' in payload.snippets)) {
				isDirty = false;
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