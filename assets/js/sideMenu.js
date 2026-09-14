// Boční menu (wide layout) - toggle sbaleno/rozbaleno, toggleable skupiny, otevírání has-submenu itemů.
// Stav se persistuje v localStorage. V sbaleném režimu se popovery skupin polohují přes CSS
// proměnnou --group-top (fixed position potřebuje inline top odpovídající Y skupiny).

const STORAGE_KEY = 'fancyadmin-side-menu';

function readState() {
	try {
		return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
	} catch (e) {
		return {};
	}
}

function writeState(state) {
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
	} catch (e) {}
}

function applyState() {
	const state = readState();
	// default = zabalené menu, aby uživatel viděl přehled skupin s ikonami; rozbalí si přes toggle
	const collapsed = state.collapsed !== false;
	const wasCollapsed = $('body').hasClass('menu-collapsed');
	$('body').toggleClass('menu-collapsed', collapsed);
	// Chrome bug: po toggle body třídy sidebar width ani descendant styly (item
	// layout, group heading, submenu popovery) se nezinvalidují - uživatel musí
	// F5. Fix má dvě části:
	//   1) inline width na sidebar (hodnoty duplikují --side-panel-width z SCSS)
	//   2) detach + immediate reattach sidebar elementu ve stejném JS turnu -
	//      vynutí full style recalc descendantů bez viditelného flashe (browser
	//      nemaluje mid-turn). Bez detach zůstane item layout ve staré verzi.
	// Skip pokud se state nezměnil (initial page load kdy preload script v
	// layout.latte už přidal menu-collapsed na body) - jinak by detach způsobil
	// jednoframový flash sidebar zmizení.
	const sidebar = document.querySelector('.side-panel-wide');
	if (sidebar && wasCollapsed !== collapsed) {
		sidebar.style.width = collapsed ? '84px' : '200px';
		if (sidebar.parentElement) {
			const parent = sidebar.parentElement;
			const next = sidebar.nextSibling;
			parent.removeChild(sidebar);
			parent.insertBefore(sidebar, next);
		}
	}

	// toggleable skupiny - sbalitelnost v rozbaleném menu
	const collapsedHeadings = state.collapsedHeadings || [];
	$('.side-panel-wide .group.toggleable').each(function () {
		const key = $(this).data('heading');
		const isCollapsed = collapsedHeadings.includes(key);
		$(this).toggleClass('collapsed', isCollapsed);
	});

	// Rozbalené submenu (level 3 inline v rozbaleném režimu).
	// Default: aktivní item (má-li child current URL) je otevřený. Uživatel může kliknutím
	// zavřít (uloží se do closedSubmenus). Explicitní open má přednost nad active-default.
	const openSubmenus = state.openSubmenus || [];
	const closedSubmenus = state.closedSubmenus || [];
	$('.side-panel-wide .item.has-submenu').each(function () {
		const label = $(this).find('.title').text().trim();
		const isActive = $(this).hasClass('active');
		let isOpen;
		if (openSubmenus.includes(label)) {
			isOpen = true;
		} else if (closedSubmenus.includes(label)) {
			isOpen = false;
		} else {
			isOpen = isActive;
		}
		$(this).toggleClass('open', isOpen);
	});

	positionPopovers();
}

// Popover je position: fixed → potřebuje inline top nastavený podle Y kotvy (heading skupiny
// nebo top-level has-submenu itemu), jinak by šel na top: 0. Nastavujeme CSS proměnnou
// --group-top / --item-top na kotvu, popover ji čte v top:.
function positionPopovers() {
	$('.side-panel-wide .group').each(function () {
		const top = this.offsetTop;
		this.style.setProperty('--group-top', top + 'px');
	});
	$('.side-panel-wide .menu-body > .item.has-submenu').each(function () {
		const top = this.offsetTop;
		this.style.setProperty('--item-top', top + 'px');
	});
}

$(document).on('click', '.side-panel-toggle', function () {
	const state = readState();
	state.collapsed = !$('body').hasClass('menu-collapsed');
	writeState(state);
	applyState();
});

$(document).on('click', '.side-panel-wide .group.toggleable .group-heading', function () {
	const key = $(this).closest('.group').data('heading');
	const state = readState();
	const collapsed = state.collapsedHeadings || [];
	const idx = collapsed.indexOf(key);
	if (idx === -1) {
		collapsed.push(key);
	} else {
		collapsed.splice(idx, 1);
	}
	state.collapsedHeadings = collapsed;
	writeState(state);
	applyState();
});

// Klik na header má-submenu itemu = toggle open/closed. Persistuje se do dvou seznamů
// (openSubmenus / closedSubmenus), aby šlo přepsat active-default z applyState.
$(document).on('click', '.side-panel-wide .item.has-submenu > .item-header', function (e) {
	if ($('body').hasClass('menu-collapsed')) {
		return;
	}
	if (e && e.preventDefault) {
		e.preventDefault();
		e.stopPropagation();
	}
	const $item = $(this).closest('.item.has-submenu');
	const label = $item.find('.title').text().trim();
	const isCurrentlyOpen = $item.hasClass('open');

	const state = readState();
	state.openSubmenus = (state.openSubmenus || []).filter(l => l !== label);
	state.closedSubmenus = (state.closedSubmenus || []).filter(l => l !== label);
	if (isCurrentlyOpen) {
		state.closedSubmenus.push(label);
	} else {
		state.openSubmenus.push(label);
	}
	writeState(state);
	applyState();
});

$(function () {
	applyState();
});

// při resize okna přepočítáme pozice popoverů (šířka sidebaru by mohla ovlivnit offsety)
$(window).on('resize', positionPopovers);

if ($.nette && $.nette.ext('live')) {
	$.nette.ext('live').after(function () {
		applyState();
	});
}
