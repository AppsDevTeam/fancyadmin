// Záložky (.nav-tabs) — přejíždějící podtržení aktivní záložky, přetečení do „Další“
// (Bootstrap dropdown) a pod 992 px posuvná lišta se šipkami (.nav-tabs-sm jen lišta).
// Volá se na DOM ready i po AJAX překreslení; hotové záložky mají data-tabs-ready.

const NARROW = window.matchMedia('(max-width: 991.98px)');

// Utility třídy okrajů patří obalu, linka je na něm
const MARGIN_CLASS = /^m[tbsexy]?-(\w+-)?(\d|auto)$/;

const html = (markup) => {
	const template = document.createElement('template');
	template.innerHTML = markup.trim();
	return template.content.firstElementChild;
};

const sum = (values) => values.reduce((total, value) => total + value, 0);

function parts(nav) {
	const wrap = nav.parentElement;
	return {
		wrap,
		prev: wrap.firstElementChild,
		next: wrap.lastElementChild,
		indicator: nav.querySelector(':scope > .nav-tabs-indicator'),
		more: nav.querySelector(':scope > .nav-tabs-more'),
		items: [...nav.querySelectorAll(':scope > .nav-item:not(.nav-tabs-more)')],
		active: nav.querySelector(':scope > .nav-item:not(.nav-tabs-more) > .nav-link.active'),
	};
}

function placeIndicator(nav, animate) {
	const { indicator, more, active } = parts(nav);
	const target = active?.parentElement.hidden ? more.firstElementChild : active;
	if (!target?.getClientRects().length) {
		nav.classList.remove('has-indicator');
		return;
	}
	// První umístění a přepočet po resize bez animace
	indicator.style.transition = animate && nav.classList.contains('has-indicator') ? '' : 'none';
	indicator.style.left = (target.getBoundingClientRect().left - nav.getBoundingClientRect().left + nav.scrollLeft) + 'px';
	indicator.style.width = target.offsetWidth + 'px';
	indicator.offsetWidth;
	indicator.style.transition = '';
	nav.classList.add('has-indicator');
}

function updateArrows(nav) {
	const { wrap, prev, next } = parts(nav);
	const scrolling = wrap.classList.contains('is-scroll');
	const canStart = scrolling && nav.scrollLeft > 1;
	const canEnd = scrolling && nav.scrollLeft < nav.scrollWidth - nav.clientWidth - 1;
	prev.disabled = !canStart;
	next.disabled = !canEnd;
	wrap.classList.toggle('can-scroll-start', canStart);
	wrap.classList.toggle('can-scroll-end', canEnd);
}

function scrollTo(nav, left, smooth) {
	nav.style.scrollBehavior = smooth ? '' : 'auto';
	nav.scrollLeft = left;
	nav.style.scrollBehavior = '';
	updateArrows(nav);
	// Plynulý posun doběhne až po chvíli
	setTimeout(() => updateArrows(nav), 350);
}

// Když je aktivní záložka v „Další“, převezme „Další“ její název
function setMore(more, link, hidden) {
	const toggle = more.firstElementChild;
	toggle.querySelector('.nav-tabs-more-label').innerHTML = link ? link.innerHTML : 'Další';
	toggle.classList.toggle('active', !!link);
	more.querySelector('.dropdown-menu').replaceChildren(...hidden.map((li) => {
		const source = li.firstElementChild;
		const item = html('<li><button type="button" class="dropdown-item"></button></li>');
		item.firstElementChild.innerHTML = source.innerHTML;
		item.firstElementChild.classList.toggle('active', source.classList.contains('active'));
		item.firstElementChild._tabLink = source;
		return item;
	}));
}

function layout(nav, animate = false) {
	const { wrap, more, items, active } = parts(nav);
	if (!wrap.getClientRects().length) {
		return;
	}

	items.forEach((li) => {
		li.hidden = false;
	});
	if (more) {
		more.hidden = true;
	}
	wrap.classList.remove('is-scroll');

	const available = wrap.clientWidth;
	const widths = items.map((li) => li.offsetWidth);
	const fits = sum(widths) <= available;

	if (more && !NARROW.matches && !fits) {
		const activeIndex = items.findIndex((li) => li.contains(active));
		more.hidden = false;
		setMore(more, null, []);
		const moreWidth = more.offsetWidth;
		setMore(more, active, []);
		const moreActiveWidth = more.offsetWidth;

		// Schovává se zprava, dokud se vejde i „Další“
		let visible = items.length - 1;
		while (visible > 0 && sum(widths.slice(0, visible)) + (activeIndex >= visible ? moreActiveWidth : moreWidth) > available) {
			visible--;
		}
		const hidden = items.slice(visible);
		hidden.forEach((li) => {
			li.hidden = true;
		});
		setMore(more, activeIndex >= visible ? active : null, hidden);
	} else if (!fits) {
		wrap.classList.add('is-scroll');
		const li = active?.parentElement;
		if (li) {
			scrollTo(nav, li.offsetLeft - (nav.clientWidth - li.offsetWidth) / 2, animate);
		}
	}

	updateArrows(nav);
	placeIndicator(nav, animate);
}

function init(nav) {
	if (nav.dataset.tabsReady) {
		return;
	}
	nav.dataset.tabsReady = '1';

	const wrap = html(`
		<div class="nav-tabs-wrap">
			<button type="button" class="nav-tabs-arrow" aria-label="Předchozí záložky" disabled><i class="fa-regular fa-chevron-left"></i></button>
			<button type="button" class="nav-tabs-arrow" aria-label="Další záložky" disabled><i class="fa-regular fa-chevron-right"></i></button>
		</div>
	`);
	[...nav.classList].filter((cls) => MARGIN_CLASS.test(cls)).forEach((cls) => {
		nav.classList.remove(cls);
		wrap.classList.add(cls);
	});
	nav.before(wrap);
	wrap.firstElementChild.after(nav);

	// dropdown-toggle vyřadí „Další“ z klávesové navigace Bootstrap Tab
	if (!nav.classList.contains('nav-tabs-sm')) {
		nav.append(html(`
			<li class="nav-item dropdown nav-tabs-more" hidden>
				<button type="button" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
					<span class="nav-tabs-more-label"></span><i class="fa-regular fa-chevron-down" aria-hidden="true"></i>
				</button>
				<ul class="dropdown-menu dropdown-menu-end"></ul>
			</li>
		`));
	}
	nav.append(html('<span class="nav-tabs-indicator" aria-hidden="true"></span>'));

	const step = (direction) => scrollTo(nav, nav.scrollLeft + direction * Math.max(120, nav.clientWidth * 0.7), true);
	wrap.firstElementChild.addEventListener('click', () => step(-1));
	wrap.lastElementChild.addEventListener('click', () => step(1));
	nav.addEventListener('scroll', () => updateArrows(nav), { passive: true });
	nav.addEventListener('shown.bs.tab', () => layout(nav, true));

	// Ve skryté záložce nejde měřit; po odkrytí se obal zvětší z nuly
	let lastWidth = null;
	new ResizeObserver(([entry]) => {
		const width = Math.round(entry.contentRect.width);
		if (width !== lastWidth) {
			lastWidth = width;
			layout(nav);
		}
	}).observe(wrap);
}

function initTabs() {
	document.querySelectorAll('.nav-tabs').forEach(init);
}

function relayoutAll() {
	document.querySelectorAll('.nav-tabs[data-tabs-ready]').forEach((nav) => layout(nav));
}

// Položka „Další“ jen zastupuje schovanou záložku
document.addEventListener('click', (e) => {
	e.target.closest('.nav-tabs-more .dropdown-item')?._tabLink.click();
});

NARROW.addEventListener('change', relayoutAll);
document.fonts?.ready.then(relayoutAll);

$(initTabs);
$.nette.ext('live').after(initTabs);

export { initTabs, relayoutAll };
