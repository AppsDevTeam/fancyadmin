// Poloha popoveru sbaleného bočního menu: začíná u své položky, ale nesmí přetéct spodek okna,
// jinak by dlouhé podmenu (např. dvacet stránek Nastavení) mělo spodní položky nedosažitelné.
// Co se nevejde ani posunuté nahoru, dostane posuvník.
export function fitPopover(anchorTop, popoverHeight, viewportHeight, margin = 8) {
	const available = viewportHeight - 2 * margin;
	const scrollable = popoverHeight > available;
	const height = Math.min(popoverHeight, available);
	const top = Math.min(anchorTop, Math.max(margin, viewportHeight - margin - height));

	return { top, scrollable };
}

// Míří myš z bodu from do bodu to k popoveru vpravo od menu? Musí jet doprava a vejít se do klínu
// mezi from a levými rohy popoveru (jquery-menu-aim počítá totéž k rohům celého menu).
export function isAimingAt(from, to, popoverRect, tolerance = 20) {
	const dx = to.x - from.x;
	const distance = popoverRect.left - from.x;
	if (dx <= 0 || distance <= 0) {
		return false;
	}

	const slope = (to.y - from.y) / dx;

	return slope >= (popoverRect.top - tolerance - from.y) / distance
		&& slope <= (popoverRect.bottom + tolerance - from.y) / distance;
}
