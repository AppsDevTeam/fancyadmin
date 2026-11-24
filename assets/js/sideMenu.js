import naja from 'naja';
import { onBeforeSnippetUpdate, onContentLoaded } from './naja';
import { fitPopover, isAimingAt } from './popoverPosition';
// Boční menu (wide layout) - toggle sbaleno/rozbaleno, toggleable skupiny, otevírání has-submenu itemů.
// Stav se persistuje v localStorage. V sbaleném režimu otevírá popovery skupin a položek s podmenu JS
// (třída menu-popover-open) a polohuje je přes CSS proměnnou --group-top / --item-top.

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
	//   1) inline width na sidebar - odkazem na --side-panel-width, ne natvrdo v px,
	//      jinak by se v JS duplikovala šířka ze SCSS (ve sbaleném režimu se počítá
	//      z velikosti čtvercové položky) a po toggle by sidebar držel starou hodnotu
	//   2) detach + immediate reattach sidebar elementu ve stejném JS turnu -
	//      vynutí full style recalc descendantů bez viditelného flashe (browser
	//      nemaluje mid-turn). Bez detach zůstane item layout ve staré verzi.
	// Skip pokud se state nezměnil (initial page load kdy preload script v
	// layout.latte už přidal menu-collapsed na body) - jinak by detach způsobil
	// jednoframový flash sidebar zmizení.
	const sidebar = document.querySelector('.side-panel-wide');
	if (sidebar && wasCollapsed !== collapsed) {
		sidebar.style.width = 'var(--side-panel-width)';
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
}

$(document).on('click', '.side-panel-toggle', function () {
	// Přepnutí šířky menu mění layout skupin a podmenu - nemá se animovat
	$('.side-panel-wide').removeClass('menu-animate');
	const state = readState();
	state.collapsed = !$('body').hasClass('menu-collapsed');
	writeState(state);
	applyState();
});

// Animace rozbalování jen po kliku uživatele, ne při načtení stránky (viz _sidepanel.scss)
function enableMenuAnimation() {
	$('.side-panel-wide').addClass('menu-animate');
}

$(document).on('click', '.side-panel-wide .group.toggleable .group-heading', function () {
	enableMenuAnimation();
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
	enableMenuAnimation();
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

// Popover sbaleného menu otevírá JS, ne :hover: myš jedoucí šikmo do otevřeného popoveru přejede
// cestou jiné položky a ty ho nesmí přepnout (princip jquery-menu-aim, ale k opravdové poloze popoveru).
const POPOVER_OPEN_CLASS = 'menu-popover-open';
const POPOVER_OWNERS = '.side-panel-wide .menu-body > .item.has-submenu, .side-panel-wide .group';
// Jak dlouho smí myš stát nad jinou položkou, než se popover přepne
const AIM_DELAY_MS = 300;
const LEAVE_DELAY_MS = 400;
const TRACKED_POSITIONS = 3;

let openOwner = null;
let hoveredOwner = null;
let aimTimer = null;
let leaveTimer = null;
const mousePositions = [];

function popoverOf(owner) {
	return owner.querySelector(owner.classList.contains('group') ? ':scope > .group-items' : ':scope > .submenu');
}

// Výšku popoveru jde změřit až otevřený; kotva podle okna, ne offsetTop - menu při nižším okně roluje
function positionPopover(owner) {
	const popover = popoverOf(owner);
	if (!popover) {
		return;
	}

	const { top, scrollable } = fitPopover(owner.getBoundingClientRect().top, popover.scrollHeight, window.innerHeight);
	owner.style.setProperty(owner.classList.contains('group') ? '--group-top' : '--item-top', top + 'px');
	popover.classList.toggle('popover-scrollable', scrollable);
}

function openPopover(owner) {
	clearTimeout(aimTimer);
	aimTimer = null;
	document.querySelectorAll('.' + POPOVER_OPEN_CLASS).forEach(element => {
		if (element !== owner) {
			element.classList.remove(POPOVER_OPEN_CLASS);
		}
	});
	openOwner = owner;

	if (owner && !owner.classList.contains(POPOVER_OPEN_CLASS)) {
		owner.classList.add(POPOVER_OPEN_CLASS);
		positionPopover(owner);
	}
}

function isAimingAtOpenPopover(position) {
	if (!openOwner?.isConnected || !mousePositions.length) {
		return false;
	}

	const popover = popoverOf(openOwner);

	return !!popover && isAimingAt(mousePositions[0], position, popover.getBoundingClientRect());
}

function restartAimTimer() {
	clearTimeout(aimTimer);
	aimTimer = setTimeout(() => openPopover(hoveredOwner), AIM_DELAY_MS);
}

document.addEventListener('mouseover', function (event) {
	if (!event.target.closest('.side-panel-wide') || !document.body.classList.contains('menu-collapsed')) {
		return;
	}

	clearTimeout(leaveTimer);
	// Mimo položky (prázdné místo pod nimi, patička) = null, popover se zavře
	hoveredOwner = event.target.closest(POPOVER_OWNERS);

	if (hoveredOwner !== openOwner && isAimingAtOpenPopover({ x: event.clientX, y: event.clientY })) {
		restartAimTimer();
	} else {
		openPopover(hoveredOwner);
	}
});

document.addEventListener('mousemove', function (event) {
	const position = { x: event.clientX, y: event.clientY };

	if (aimTimer !== null) {
		if (isAimingAtOpenPopover(position)) {
			restartAimTimer();
		} else {
			openPopover(hoveredOwner);
		}
	}

	mousePositions.push(position);
	if (mousePositions.length > TRACKED_POSITIONS) {
		mousePositions.shift();
	}
});

document.addEventListener('mouseout', function (event) {
	if (event.target.closest('.side-panel-wide') && !event.relatedTarget?.closest?.('.side-panel-wide')) {
		clearTimeout(aimTimer);
		aimTimer = null;
		clearTimeout(leaveTimer);
		leaveTimer = setTimeout(() => openPopover(null), LEAVE_DELAY_MS);
	}
});

$(function () {
	applyState();
});

$(window).on('resize', function () {
	if (openOwner?.isConnected) {
		positionPopover(openOwner);
	}
});

onContentLoaded(function () {
	applyState();
});

// Menu je ve snippetu body, takže ho každý ajaxový přechod vymění za nový element a odscrollované
// menu by skočilo nahoru. Zvýraznění položek ale musí dál počítat server, proto se menu překresluje
// a jen se mu vrátí scroll.
let menuScrollTop = null;

onBeforeSnippetUpdate(function ($el) {
	const menuBody = $el.find('.side-panel-wide .menu-body').get(0);
	if (menuBody) {
		menuScrollTop = menuBody.scrollTop;
	}
});

naja.snippetHandler.addEventListener('afterUpdate', function (e) {
	const menuBody = $(e.detail.snippet).find('.side-panel-wide .menu-body').get(0);
	if (menuBody && menuScrollTop !== null) {
		menuBody.scrollTop = menuScrollTop;
		menuScrollTop = null;
	}
});
