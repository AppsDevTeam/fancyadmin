// Tlačítko s data-adt-copy-text="<text>" zkopíruje text do schránky a potvrdí to hláškou
// (data-adt-copy-text-message, jinak „Zkopírováno do schránky“)
import { showFlash } from './flashes';

document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-adt-copy-text]');
	if (!button) {
		return;
	}

	event.preventDefault();
	navigator.clipboard.writeText(button.dataset.adtCopyText)
		.then(() => showFlash(button.dataset.adtCopyTextMessage || 'Zkopírováno do schránky', 'success', 3000));
});
