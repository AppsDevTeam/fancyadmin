import naja from './naja';

// Výběr v menu rychlé lišty na telefonu (UserMenuSelect, @quickbarMenu.latte):
// změna otevře ajaxem odkaz vybrané volby (signál hodnotu uloží a přesměruje) - jako klik
// na odkaz, takže se adresa zapíše do historie.
document.addEventListener('change', (e) => {
	const select = e.target.closest('[data-fancyadmin-user-menu-select] select');
	const url = select?.selectedOptions[0]?.dataset.url;
	if (!url) {
		return;
	}
	const link = document.createElement('a');
	link.href = url;
	naja.uiHandler.clickElement(link).catch(() => {});
});

// Výběr v menu u uživatele (@userButton.latte) je řádek se seznamem voleb, který vyjede
// po najetí myší. Na dotykovém zařízení najetí není - klik na řádek seznam otevře a zavře.
// Klik na řádek nesmí zavřít celé menu (Bootstrap poslouchá na document), volba je odkaz.
document.addEventListener('click', (e) => {
	const row = e.target.closest?.('[data-fancyadmin-user-menu-submenu]');
	if (!row || e.target.closest('.user-menu-submenu-list a')) {
		return;
	}
	e.stopPropagation();
	row.classList.toggle('open');
}, true);
