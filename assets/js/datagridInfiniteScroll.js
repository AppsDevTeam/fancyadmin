const GRID_SELECTOR = '.datagrid.datagrid-infinite-scroll';
const LOAD_MORE_SELECTOR = '[data-datagrid-infinite-scroll]';

const LOAD_MORE_MARGIN = 300;

const SHOW_HEADER_DELTA = 10;

const HEADER_ANIMATION = 250;

let observer = null;
let observerRoot;

function getScrollContainer() {
	return document.querySelector('[data-app-container]');
}

function getObserver() {
	const root = getScrollContainer();
	if (!observer || observerRoot !== root) {
		observer?.disconnect();
		observerRoot = root;
		observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					loadMore(entry.target);
				}
			});
		}, { root, rootMargin: `0px 0px ${LOAD_MORE_MARGIN}px 0px` });
	}
	return observer;
}

function loadMore(link) {
	if (link.dataset.datagridInfiniteScrollLoading || !link.isConnected) {
		return;
	}
	link.dataset.datagridInfiniteScrollLoading = '1';
	link.classList.add('disabled');
	getObserver().unobserve(link);
	link.click();
}

function observeLoadMore(el) {
	el.querySelectorAll(`${GRID_SELECTOR} ${LOAD_MORE_SELECTOR}`).forEach((link) => {
		getObserver().observe(link);
	});
}

const HEADER_PINNED_CLASS = 'datagrid-page-header-pinned';
const HEADER_HIDDEN_CLASS = 'datagrid-page-header-hidden';
const HEADER_INSTANT_CLASS = 'datagrid-page-header-instant';

let lastScrollTop = 0;
let animationEnd = 0;
let frame = null;

function getStickyGrid() {
	return document.querySelector(GRID_SELECTOR);
}

function getPageHeader(grid) {
	const content = grid.closest('#content');
	return content ? content.querySelector(':scope > .header') : null;
}

function getScrollTop(container) {
	return container ? container.scrollTop : window.scrollY;
}

function getContainerTop(container) {
	return container ? container.getBoundingClientRect().top : 0;
}

function setHeaderState(target, pinned, hidden, animate) {
	const wasPinned = target.classList.contains(HEADER_PINNED_CLASS);
	const wasHidden = target.classList.contains(HEADER_HIDDEN_CLASS);
	if (wasPinned === pinned && wasHidden === hidden) {
		return;
	}

	if (!pinned && !hidden) {
		target.classList.remove(HEADER_PINNED_CLASS, HEADER_HIDDEN_CLASS);
	} else if (pinned && !wasPinned) {
		target.classList.add(HEADER_INSTANT_CLASS, HEADER_PINNED_CLASS, HEADER_HIDDEN_CLASS);
		void target.offsetHeight;
		target.classList.remove(HEADER_INSTANT_CLASS);
		target.classList.toggle(HEADER_HIDDEN_CLASS, hidden);
	} else {
		target.classList.toggle(HEADER_HIDDEN_CLASS, hidden);
	}

	if (!animate) {
		target.classList.add(HEADER_INSTANT_CLASS);
		void target.offsetHeight;
		target.classList.remove(HEADER_INSTANT_CLASS);
	}
	animationEnd = performance.now() + HEADER_ANIMATION;
}

function updateHeaderVisibility(container, grid) {
	const target = container || document.documentElement;
	const header = getPageHeader(grid);
	const scrollTop = getScrollTop(container);
	const delta = scrollTop - lastScrollTop;

	if (!header) {
		setHeaderState(target, false, false, false);
		lastScrollTop = scrollTop;
		return;
	}

	const pinned = target.classList.contains(HEADER_PINNED_CLASS);
	const hidden = target.classList.contains(HEADER_HIDDEN_CLASS);

	const naturalTop = header.parentElement.offsetTop;
	const naturalBottom = header.parentElement.offsetTop + header.offsetHeight;

	if (scrollTop <= naturalTop) {
		setHeaderState(target, false, false, false);
	} else if (pinned && hidden && scrollTop < naturalBottom) {
		setHeaderState(target, false, false, false);
	} else if (delta > 0) {
		if (pinned && !hidden) {
			setHeaderState(target, true, true, true);
		}
	} else if (delta < -SHOW_HEADER_DELTA) {
		if (scrollTop >= naturalBottom && (!pinned || hidden)) {
			setHeaderState(target, true, false, true);
		}
	} else {
		return;
	}

	lastScrollTop = scrollTop;
}

function updateStickyOffsets(container, grid) {
	const target = container || document.documentElement;
	const header = getPageHeader(grid);

	const paddingTop = parseFloat(getComputedStyle(target).paddingTop || 0);
	target.style.setProperty('--datagridStickyTop', -paddingTop + 'px');

	const headerVisible = header && target.classList.contains(HEADER_PINNED_CLASS) && !target.classList.contains(HEADER_HIDDEN_CLASS);
	const headerHeight = headerVisible ? header.offsetHeight : 0;
	target.style.setProperty('--datagridStickyFilterTop', headerHeight + 'px');

	const filter = grid.querySelector('.main-search-filter');
	target.style.setProperty('--datagridStickyFilterHeight', (filter ? filter.offsetHeight : 0) + 'px');

	updateStickyThead(grid);
}

const STICKY_THEAD_CLASS = 'datagrid-sticky-thead';
const STICKY_THEAD_STUCK_CLASS = 'is-stuck';

let widthsDirty = true;

function getGridTable(grid) {
	const wrapper = grid.querySelector('.table-responsive-wrapper');
	const responsive = wrapper?.querySelector(':scope > .table-responsive');
	const table = responsive?.querySelector(':scope > table');
	const thead = table?.querySelector(':scope > thead');

	return thead ? { wrapper, responsive, table, thead } : null;
}

function proxyCloneInputs(clone, thead) {
	const originals = thead.querySelectorAll('input, select, textarea, button');
	clone.querySelectorAll('input, select, textarea, button').forEach((input, i) => {
		input.removeAttribute('name');
		input.addEventListener('click', (e) => {
			e.preventDefault();
			originals[i]?.click();
			setTimeout(() => {
				if ('checked' in input && originals[i]) {
					input.checked = originals[i].checked;
				}
			});
		});
	});
}

function buildStickyThead(grid) {
	const parts = getGridTable(grid);
	if (!parts) {
		return null;
	}

	let sticky = parts.wrapper.querySelector(':scope > .' + STICKY_THEAD_CLASS);
	if (sticky && sticky.datagridSource === parts.thead) {
		return sticky;
	}
	sticky?.remove();

	const clone = $(parts.thead).clone(true, true)[0];
	clone.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
	proxyCloneInputs(clone, parts.thead);

	const cloneTable = document.createElement('table');
	cloneTable.className = parts.table.className;
	cloneTable.appendChild(clone);

	const inner = document.createElement('div');
	inner.className = 'table-responsive ' + STICKY_THEAD_CLASS + '__inner';
	inner.appendChild(cloneTable);

	sticky = document.createElement('div');
	sticky.className = STICKY_THEAD_CLASS;
	sticky.setAttribute('aria-hidden', 'true');
	sticky.appendChild(inner);
	sticky.datagridSource = parts.thead;

	parts.wrapper.insertBefore(sticky, parts.responsive);
	parts.responsive.addEventListener('scroll', () => {
		inner.scrollLeft = parts.responsive.scrollLeft;
	}, { passive: true });

	widthsDirty = true;
	return sticky;
}

function syncStickyTheadWidths(sticky, parts) {
	const cloneTable = sticky.querySelector('table');
	cloneTable.style.width = parts.table.getBoundingClientRect().width + 'px';

	const widths = Array.from(parts.thead.querySelectorAll('th'), (th) => th.getBoundingClientRect().width);
	sticky.querySelectorAll('th').forEach((th, i) => {
		th.style.width = th.style.minWidth = th.style.maxWidth = widths[i] + 'px';
	});

	sticky.firstElementChild.scrollLeft = parts.responsive.scrollLeft;
}

function updateStickyThead(grid) {
	const sticky = buildStickyThead(grid);
	if (!sticky) {
		return;
	}
	const parts = getGridTable(grid);

	if (widthsDirty) {
		widthsDirty = false;
		syncStickyTheadWidths(sticky, parts);
	}

	const stuck = parts.thead.getBoundingClientRect().top < sticky.getBoundingClientRect().top - 1;
	sticky.classList.toggle(STICKY_THEAD_STUCK_CLASS, stuck);
}

function update() {
	frame = null;
	const grid = getStickyGrid();
	const container = getScrollContainer();
	if (!grid) {
		(container || document.documentElement).classList.remove(HEADER_PINNED_CLASS, HEADER_HIDDEN_CLASS);
		return;
	}
	updateHeaderVisibility(container, grid);
	updateStickyOffsets(container, grid);

	if (performance.now() < animationEnd) {
		scheduleUpdate();
	}
}

function scheduleUpdate() {
	if (frame === null) {
		frame = requestAnimationFrame(update);
	}
}

let boundContainer;

function bindScroll() {
	const container = getScrollContainer();
	if (boundContainer === container) {
		return;
	}
	(boundContainer || window).removeEventListener?.('scroll', scheduleUpdate);
	boundContainer = container;
	(container || window).addEventListener('scroll', scheduleUpdate, { passive: true });
}

window.addEventListener('resize', () => {
	widthsDirty = true;
	scheduleUpdate();
});

$.nette.ext('live').after(function ($el) {
	widthsDirty = true;
	observeLoadMore($el[0] || document);
	bindScroll();
	scheduleUpdate();
});
