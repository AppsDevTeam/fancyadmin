(function ($, undefined) {

	// Is History API reliably supported? (based on Modernizr & PJAX)
	if (!(window.history && history.pushState && window.history.replaceState)) {
		return;
	}

	$.nette.ext('redirect', false);

	var findSnippets = function () {
		var result = [];
		$('[id^="snippet-"]').each(function () {
			var $el = $(this);
			if (!$el.is('[data-history-nocache]')) {
				result.push({
					id: $el.attr('id'),
					html: $el.html()
				});
			}
		});
		return result;
	};
	var handleState = function (context, name, args) {
		var handler = context['handle' + name.substring(0, 1).toUpperCase() + name.substring(1)];
		if (handler) {
			handler.apply(context, args);
		}
	};

	$.nette.ext('history', {
		init: function () {
			var snippetsExt;
			if (this.cache && (snippetsExt = $.nette.ext('snippets'))) {
				this.handleUI = function (domCache) {
					var snippets = {};
					$.each(domCache, function () {
						snippets[this.id] = this.html;
					});
					snippetsExt.updateSnippets(snippets, true);
					$.nette.load();
				};
			}

			this.popped = !!('state' in window.history) && !!window.history.state;
			var initialUrl = window.location.href;

			$(window).on('popstate.nette', $.proxy(function (e) {
				var state = e.originalEvent.state || this.initialState;
				var initialPop = (!this.popped && initialUrl === state.href);
				this.popped = true;
				if (initialPop || !e.originalEvent.state) {
					return;
				}
				if (this.cache && state.ui) {
					handleState(this, 'UI', [state.ui]);
					handleState(this, 'title', [state.title]);
				} else {
					$.nette.ajax({
						url: state.href,
						off: ['history']
					});
				}
			}, this));

			history.replaceState(this.initialState = {
				nette: true,
				href: window.location.href,
				title: document.title,
				ui: this.cache ? findSnippets() : null
			}, document.title, window.location.href);
		},
		before: function (xhr, settings) {
			if (!settings.nette) {
				this.href = null;
			} else if (!settings.nette.form) {
				this.href = settings.nette.ui.href;
			} else if (settings.nette.form.get(0).method === 'get') {
				this.href = settings.nette.form.get(0).action || window.location.href;
			} else {
				this.href = null;
			}
		},
		success: function (payload) {
			var redirect = payload.redirect || payload.url; // backwards compatibility for 'url'
			if (redirect) {
				var regexp = new RegExp('//' + window.location.host + '($|/)');
				if ((redirect.substring(0,4) === 'http') ? regexp.test(redirect) : true) {
					this.href = redirect;
				} else {
					window.location.href = redirect;
				}
			}

			if (this.href) {
				const url = new URL(this.href);
				const hasDoParameter = url.searchParams.has('do');
				console.log([
					this.href !== window.location.href, !hasDoParameter
				]);
				if (this.href !== window.location.href && !hasDoParameter) {
					history.pushState({
						nette: true,
						href: this.href,
						title: document.title,
						ui: this.cache ? findSnippets() : null
					}, document.title, this.href);

					const url = new URL(this.href);
					$('a.nav-link').map((i, el) => {
						if ($(el).attr('href') === '/') {
							$(el).toggleClass('active', url.pathname === $(el).attr('href'))
						} else {
							$(el).toggleClass('active', url.pathname.includes($(el).attr('href')))
						}
					});

					$('.sub-menu > a').map((i, el) => {
						if ($(el).attr('href') === '/') {
							$(el).toggleClass('active', url.pathname === $(el).attr('href'))
						} else {
							$(el).toggleClass('active', url.pathname.includes($(el).attr('href')))
						}
					});

					$('.bottom-mobile-menu a:not(.sub-menu-toggle)').map((i, el) => {
						const isOpened = $(el).attr('href') === '/'
							? (url.pathname === '/')
							: (url.pathname.includes($(el).attr('href')));
						if (isOpened) {
							$('.bottom-mobile-menu a').map((i, el) => {
								$(el).toggleClass('isOpened', false)
							});
						}

						$(el).toggleClass('isOpened', isOpened)
					});
				}
			}
			if (redirect) {
				$.nette.ajax({
					url: this.href,
					off: ['history']
				});
			}
			this.href = null;
			this.popped = true;
		}
	}, {
		href: null,
		cache: true,
		popped: false,
		handleTitle: function (title) {
			document.title = title;
		}
	});

})(jQuery);