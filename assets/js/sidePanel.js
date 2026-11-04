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
					// Až po vložení snippetů s hláškami
					const container = document.getElementById(panelId).closest('.side-panel-template-container');
					setTimeout(() => showErrorTab(container));
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

	// Nadpis nového záznamu je text tlačítka, které panel otevřelo; ikonové tlačítko dá svůj popisek
	function triggerTitle(el) {
		const text = el.matches('.btn, .dropdown-item') ? el.textContent.replace(/\s+/g, ' ').trim() : '';
		return text || el.getAttribute('title') || el.getAttribute('data-bs-original-title') || el.getAttribute('aria-label') || '';
	}

	// prepare dostává stejné settings jako success, before jen kopii od jQuery
	$.nette.ext('sidePanelTitle', {
		prepare: function (settings) {
			const el = settings && settings.nette && settings.nette.el && settings.nette.el[0];
			if (!el) return;
			const stacked = el.matches('[data-fancyadmin-side-panel-stacked]');
			// Odeslání formuláře z panelu nadpis nemění
			if (!stacked && el.closest('.side-panel-template-container')) return;
			settings.sidePanelTitle = { stacked, title: triggerTitle(el) };
		},
		success: function (payload, status, xhr, settings) {
			const pending = settings && settings.sidePanelTitle;
			const snippet = pending && (pending.stacked ? 'snippet--sidePanelStacked' : 'snippet--sidePanel');
			if (!pending || !payload || !payload.snippets || !(snippet in payload.snippets)) return;
			const container = document.getElementById(snippet).closest('.side-panel-template-container');
			container.dataset.triggerTitle = pending.title;
			applyTitle(container);
		}
	});

	// Úprava má název záznamu ze serveru (.side-panel-config[data-title]), jinak text tlačítka
	function applyTitle(container) {
		const title = container.querySelector('.side-panel-template-title');
		const config = container.querySelector('[id^="snippet--sidePanel"] .side-panel-config');
		if (title) {
			title.textContent = (config && config.dataset.title) || container.dataset.triggerTitle || '';
		}
	}

	// Záložky ze začátku formuláře patří do hlavičky pod nadpis
	function buildHeaderTabs(container) {
		let row = container.querySelector('.side-panel-template-tabs');
		const tabs = container.querySelector('[id^="snippet--sidePanel"] form > :is(.nav-tabs-wrap, .nav-tabs)');
		if (!tabs) {
			// Záložky formuláře, který už v panelu není (zavření, jiný obsah)
			if (row && !container.contains(row.form)) row.remove();
			return;
		}

		if (!row) {
			row = document.createElement('div');
			row.className = 'side-panel-template-tabs';
			container.querySelector('.side-panel-template-header').after(row);
		}
		row.form = tabs.closest('form');
		// Okraje patřily místu ve formuláři, v hlavičce je řídí řada
		[...tabs.classList].filter((cls) => /^m[tbsexy]?-/.test(cls)).forEach((cls) => tabs.classList.remove(cls));
		row.replaceChildren(tabs);
		showErrorTab(container);
	}

	// Chyba v jiné záložce přepne na ni; klientská je jen v Nette.formErrors, serverová jen ve snippetu *-errors
	function showErrorTab(container, afterSubmit = false) {
		const form = container.querySelector('[id^="snippet--sidePanel"] form');
		if (!form) return;
		const clientErrors = afterSubmit && window.Nette ? (window.Nette.formErrors || []).map((error) => error.element) : [];
		const invalid = clientErrors.find((el) => el && form.contains(el))
			|| form.querySelector('.is-invalid')
			|| [...form.querySelectorAll('[id$="-errors"]')].find((el) => el.textContent.trim());
		const pane = invalid && invalid.closest('.tab-pane:not(.active)');
		const tab = pane && pane.id && container.querySelector('.side-panel-template-tabs [data-bs-target="#' + pane.id + '"]');
		if (tab) tab.click();
	}

	// Změna hodnoty v inputu → dirty. Obsah, který si ukládá sám (např. AI chat),
	// se z toho vyjímá atributem data-fancyadmin-side-panel-no-dirty.
	$(document).on('change input', '[id^="snippet--sidePanel"] form :input', function () {
		if ($(this).closest('[data-fancyadmin-side-panel-no-dirty]').length) {
			return;
		}
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

	// Spodní lišta panelu: [přepínač] ....... [Uložit]. Renderer vypisuje tlačítko
	// a přepínač nad ním jako samostatné bloky na konci formuláře, takže u delších
	// formulářů odjedou pod přehyb. Složíme je do jednoho pruhu, zbytek dělá CSS.
	function buildFormActions(container) {
		container.querySelectorAll('[id^="snippet--sidePanel"] form').forEach((form) => {
			if (form.querySelector(':scope > .side-panel-actions')) return;
			// formuláře uvnitř vlastní šablony panelu (setBodyTemplate) mají vlastní layout
			if (form.closest('.side-panel-body')) return;

			// Skrytý submit je jen nosič signálu
			const buttons = form.querySelectorAll('[type="submit"]:not([hidden])');
			const button = buttons[buttons.length - 1];
			if (!button) return;

			// Tlačítko i s obalem, ale bez polí - sekce může obalit celý formulář (identita)
			const fields = ':is(input:not([type="hidden"], [type="submit"], [type="button"], [type="reset"], [type="image"]), select, textarea)';
			let submit = button;
			while (submit.parentElement !== form && !submit.parentElement.querySelector(fields)) {
				submit = submit.parentElement;
			}

			// Lišta musí být přímo ve formuláři, jinak ji CSS nedotlačí dolů
			let anchor = submit;
			while (anchor.parentElement !== form) {
				anchor = anchor.parentElement;
			}

			// Stav záznamu bereme do lišty, jen když stojí těsně nad tlačítkem; jinde ve
			// formuláři patří ke kontextu okolních polí. V liště ho kreslíme jako přepínač
			// (form-switch je bootstrapí třída pro vzhled, na odesílaná data nemá vliv).
			const above = submit.previousElementSibling;
			const toggle = above && above.querySelector('input[name="isActive"]') ? above : null;

			const actions = document.createElement('div');
			actions.className = 'side-panel-actions';
			if (anchor === submit) {
				submit.before(actions);
			} else {
				anchor.after(actions);
			}

			if (toggle) {
				toggle.classList.add('side-panel-actions-toggle');
				toggle.querySelector('.form-check').classList.add('form-switch');
				actions.append(toggle);
			}
			submit.classList.add('side-panel-actions-submit');
			actions.append(submit);

			// Posouvá se jen obal polí, lišta pod ním zůstává na místě
			const scroll = document.createElement('div');
			scroll.className = 'side-panel-scroll';
			scroll.append(...Array.from(form.childNodes).filter((node) => node !== actions));
			form.prepend(scroll);
		});
	}

	// Linka pod hlavičkou až po odrolování, nad lištou jen dokud je pod ní další obsah
	function updateLines(container) {
		const scroller = container.querySelector('.side-panel-scroll') || container.querySelector('[id^="snippet--sidePanel"]');
		if (!scroller) return;
		container.classList.toggle('side-panel-template-container--scrolled', scroller.scrollTop > 0);
		container.classList.toggle('side-panel-template-container--more', scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight - 1);
	}

	// Otevřený stav panelů jako třídy - CSS se podle nich vysouvá a ztmavuje pozadí.
	// Přes :has(form) se změna v Chrome projeví naráz a přechod neproběhne. Třída se
	// nastavuje až o dva snímky později: vložený obsah přepočítá :has() pravidla
	// kontejneru (velikost panelu) a změna třídy ve stejném snímku by přechod zahodila.
	// Sleduje se celé body, protože panely jsou uvnitř snippetu body.
	function applyPanelState() {
		const main = document.querySelector('.side-panel-template-container:not(.side-panel-template-container--stacked)');
		const stacked = document.querySelector('.side-panel-template-container--stacked');
		const isOpen = (container) => !!container && !!container.querySelector('[id^="snippet--sidePanel"] :is(form, .side-panel-body, .side-panel-loading)');
		const mainOpen = isOpen(main);
		const stackedOpen = isOpen(stacked);

		[[main, mainOpen], [stacked, stackedOpen]].forEach(([container, open]) => {
			if (!container) return;
			if (open) {
				applyTitle(container);
				buildHeaderTabs(container);
				buildFormActions(container);
				updateLines(container);
				// Šířku určuje velikost z obsahu (:has v CSS), zapamatujeme ji pro zavírání
				container.style.removeProperty('--width');
				container.dataset.openWidth = getComputedStyle(container).maxWidth;
			}
			if (!open) {
				buildHeaderTabs(container);
			}
			container.classList.toggle('side-panel-template-container--open', open);
			const backdrop = container.previousElementSibling;
			if (backdrop && backdrop.classList.contains('side-panel-template-backdrop')) {
				backdrop.classList.toggle('side-panel-template-backdrop--open', open);
			}
		});
		if (main) {
			main.classList.toggle('side-panel-template-container--covered', mainOpen && stackedOpen);
		}
	}

	// Zavřením zmizí obsah i s velikostí panelu a širší panel by se před odjetím naráz
	// zúžil. Šířku podržíme inline hned v callbacku observeru (ještě před vykreslením).
	function holdClosingWidth() {
		document.querySelectorAll('.side-panel-template-container--open').forEach((container) => {
			if (container.dataset.openWidth && !container.querySelector('[id^="snippet--sidePanel"] :is(form, .side-panel-body, .side-panel-loading)')) {
				container.style.setProperty('--width', container.dataset.openWidth);
			}
		});
	}

	let syncScheduled = false;
	function syncPanelState() {
		holdClosingWidth();
		if (syncScheduled) return;
		syncScheduled = true;
		requestAnimationFrame(() => requestAnimationFrame(() => {
			syncScheduled = false;
			applyPanelState();
		}));
	}

	new MutationObserver(syncPanelState).observe(document.body, { childList: true, subtree: true });

	// scroll nebublá, proto zachycení na dokumentu
	document.addEventListener('scroll', (e) => {
		const container = e.target instanceof Element && e.target.closest('.side-panel-template-container');
		if (container) updateLines(container);
	}, true);
	// Přepnutí záložky v hlavičce začne nový obsah od začátku
	$(document).on('shown.bs.tab', '.side-panel-template-container', function (e) {
		if (e.target.closest('.side-panel-template-tabs')) {
			const scroller = this.querySelector('.side-panel-scroll') || this.querySelector('[id^="snippet--sidePanel"]');
			if (scroller) scroller.scrollTop = 0;
		}
		updateLines(this);
	});
	// Klientská validace neodešle formulář, chybu ale může mít v jiné záložce; capture, nette.ajax klik dál nepouští
	document.addEventListener('click', (e) => {
		const container = e.target instanceof Element && e.target.closest('.side-panel-template-container [type="submit"]')?.closest('.side-panel-template-container');
		if (container) setTimeout(() => showErrorTab(container, true));
	}, true);
	window.addEventListener('resize', () => {
		document.querySelectorAll('.side-panel-template-container--open').forEach(updateLines);
	});
	applyPanelState();

	$(document).on('click', '.side-panel-template-backdrop:not(.side-panel-template-backdrop--stacked)', () => tryClose('snippet--sidePanel'));
	$(document).on('click', '.side-panel-template-backdrop--stacked', () => tryClose('snippet--sidePanelStacked'));
	$(document).on('click', '.side-panel-template-close', function () {
		tryClose(getPanelId(this));
	});
});
