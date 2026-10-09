$(function () {
	const panels = {
		'snippet--sidePanel': { isDirty: false },
		'snippet--sidePanelStacked': { isDirty: false },
	};

	let $stackedTarget = null;

	function getPanelId(el) {
		const $snippet = $(el).closest('.side-panel-template-container').find('[id^="snippet--sidePanel"]').first();
		return $snippet.length ? $snippet.attr('id') : null;
	}

	function closeSidePanel(panelId) {
		$('#' + panelId).html('');
		panels[panelId].isDirty = false;
		if (panelId === 'snippet--sidePanelStacked') {
			$stackedTarget = null;
		}
	}

	function tryClose(panelId) {
		if (!panelId || !panels[panelId]) return;
		const confirmMessage = $('#' + panelId + ' .side-panel-config').data('close-confirm') || 'Close without saving?';
		if (panels[panelId].isDirty && !confirm(confirmMessage)) return;
		closeSidePanel(panelId);
	}

	function getFormPanelId(settings) {
		if (!(settings && settings.nette && settings.nette.form && settings.nette.form.closest)) {
			return null;
		}
		for (const panelId in panels) {
			if (settings.nette.form.closest('#' + panelId).length) {
				return panelId;
			}
		}
		return null;
	}

	function fillStackedTarget(result) {
		if (!result || !$stackedTarget || !$stackedTarget.length) return;

		if ($stackedTarget.is('select')) {
			const value = String(result.value);
			let $option = $stackedTarget.find('option').filter(function () {
				return this.value === value;
			});
			if (!$option.length) {
				$option = $(new Option(result.label, value));
				$stackedTarget.append($option);
			}
			if ($stackedTarget.prop('multiple')) {
				$option.prop('selected', true);
			} else {
				$stackedTarget.val(value);
			}
		} else {
			$stackedTarget.val(result.value);
		}
		$stackedTarget.trigger('change');
	}

	// Reset dirty řešíme už v "before" fázi odeslání formuláře z panelu.
	// Důvod: po úspěchu může jiná extension (submitForm) ve své "success"
	// fázi panel rovnou zavřít (např. po stažení souboru), a to dřív, než by
	// se sem stihl dostat "success" – reset by tak přišel pozdě a vyskočil by
	// potvrzovací dialog "Opravdu zavřít bez uložení?".
	$.nette.ext('sidePanelDirty', {
		before: function (xhr, settings) {
			// Optimisticky bereme odeslání panel formuláře jako "uloženo".
			const panelId = getFormPanelId(settings);
			if (panelId) {
				panels[panelId].isDirty = false;
			}
		},
		success: function (payload, status, xhr, settings) {
			// Validace na serveru selhala → formulář zůstává rozdělaný.
			if (payload && payload.hasErrors) {
				const panelId = getFormPanelId(settings);
				if (panelId) {
					panels[panelId].isDirty = true;
				}
				return;
			}
			// Otevření panelu / standardní uložení s překreslením snippetu → čisté.
			for (const panelId in panels) {
				if (payload && payload.snippets && (panelId in payload.snippets)) {
					panels[panelId].isDirty = false;
				}
			}
			if (payload && payload.sidePanelStackedResult) {
				fillStackedTarget(payload.sidePanelStackedResult);
				panels['snippet--sidePanel'].isDirty = true;
				$stackedTarget = null;
			}
		}
	});

	$.nette.ext('sidePanelStackedOpen', {
		before: function (xhr, settings) {
			const $el = settings && settings.nette && settings.nette.el;
			if (!$el || !$el.is('[data-fancyadmin-side-panel-stacked]')) {
				return;
			}
			$stackedTarget = $el.closest('.input-group').find('select, input:not([type="hidden"]), textarea').first();
			$('#snippet--sidePanelStacked').html('<div class="side-panel-loading"><div class="spinner-border" role="status"></div></div>');
		},
		complete: function () {
			$('#snippet--sidePanelStacked > .side-panel-loading').remove();
		}
	});

	// Změna hodnoty v inputu → dirty
	$(document).on('change input', '[id^="snippet--sidePanel"] form :input', function () {
		const panelId = getPanelId(this);
		if (panelId && panels[panelId]) {
			panels[panelId].isDirty = true;
		}
	});

	// Přidání / odebrání řádku replikátorem → dirty
	$(document).on('click', '[id^="snippet--sidePanel"] [data-adt-replicator-add], [id^="snippet--sidePanel"] [data-adt-replicator-remove]', function () {
		const panelId = getPanelId(this);
		if (panelId && panels[panelId]) {
			panels[panelId].isDirty = true;
		}
	});

	$(document).on('click', '.side-panel-template-backdrop:not(.side-panel-template-backdrop--stacked)', () => tryClose('snippet--sidePanel'));
	$(document).on('click', '.side-panel-template-backdrop--stacked', () => tryClose('snippet--sidePanelStacked'));
	$(document).on('click', '.side-panel-template-container .btn-close', function () {
		tryClose(getPanelId(this));
	});
});
