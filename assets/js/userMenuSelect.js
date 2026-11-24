import naja, { onContentLoaded } from './naja';

// Výběr v uživatelském menu (UserMenuSelect, @userButton.latte / @quickbarMenu.latte):
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

// Tom Select je z divů a klik do něj by Bootstrap bral jako klik do menu a dropdown
// zavřel (výjimku mají jen nativní pole). Bootstrap poslouchá na document - klik se
// zastaví na obalu výběru, až ho Tom Select uvnitř zpracuje. Menu se překresluje
// ajaxem (snippet body), proto se váže při každém načtení obsahu.
onContentLoaded(() => {
	document.querySelectorAll('.user-dropdown [data-fancyadmin-user-menu-select]').forEach((wrapper) => {
		if (wrapper.dataset.userMenuSelectBound) {
			return;
		}
		wrapper.dataset.userMenuSelectBound = '1';
		wrapper.addEventListener('click', (e) => e.stopPropagation());
	});
});
