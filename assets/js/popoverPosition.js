// Poloha popoveru sbaleného bočního menu: začíná u své položky, ale nesmí přetéct spodek okna,
// jinak by dlouhé podmenu (např. dvacet stránek Nastavení) mělo spodní položky nedosažitelné.
// Co se nevejde ani posunuté nahoru, dostane posuvník.
export function fitPopover(anchorTop, popoverHeight, viewportHeight, margin = 8) {
	const available = viewportHeight - 2 * margin;
	const scrollable = popoverHeight > available;
	const height = Math.min(popoverHeight, available);
	const top = Math.min(anchorTop, Math.max(margin, viewportHeight - margin - height));

	return { top, bridgeTop: anchorTop - top, scrollable };
}
