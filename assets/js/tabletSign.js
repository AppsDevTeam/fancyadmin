//
// Stránka s podpisem dokumentu na tabletu (TabletDevicePresenterTrait::actionSign, docs/tablets.md).
//
// - [data-tablet-document="<url PDF>"]: náhled dokumentu - všechny stránky pod sebou (pdf.js).
// - [data-biometric-signature]: plátno podpisu se záznamem biometriky (adt/biometric-signature).
// - form[data-tablet-sign-form]: bez podpisu se neodešle, po odeslání se tlačítko zamkne.
//
// Stránka je samostatná, bez bundlu projektu: `pdfjs`, `signature_pad` a
// `@biometric-signature/…` jsou přibalené ve fancyadminu a jejich adresy dává import mapa
// v layoutu tabletu (@tablet.latte).
//
import * as pdfjsLib from 'pdfjs';
import { initBiometricSignatures } from '@biometric-signature/biometric-signature.js';

/**
 * Vykreslí PDF do kontejneru po stránkách pod sebou. Stránky přibývají postupně shora,
 * tablet tak nevykresluje celý dokument najednou.
 *
 * @returns {Promise<number>} počet stránek, 0 = dokument se nepodařilo načíst
 */
export async function initTabletDocument(container) {
	pdfjsLib.GlobalWorkerOptions.workerSrc = container.dataset.pdfWorker;
	// ostrost podle displeje tabletu, strop kvůli paměti u dlouhých dokumentů
	const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

	let pdf;
	try {
		pdf = await pdfjsLib.getDocument({ url: container.dataset.tabletDocument }).promise;
	} catch (e) {
		console.error(e);
		container.dataset.tabletDocumentState = 'error';
		return 0;
	}

	container.querySelector('[data-tablet-document-loading]')?.remove();
	for (let number = 1; number <= pdf.numPages; number++) {
		const page = await pdf.getPage(number);
		const canvas = document.createElement('canvas');
		canvas.className = 'tablet-document-page';
		container.appendChild(canvas);

		// plátno v rozlišení, ve kterém se skutečně zobrazí (šířku omezuje CSS)
		const width = page.getViewport({ scale: 1 }).width;
		const viewport = page.getViewport({ scale: (canvas.clientWidth || 800) / width * pixelRatio });
		canvas.width = viewport.width;
		canvas.height = viewport.height;
		await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
	}
	container.dataset.tabletDocumentState = 'ready';

	return pdf.numPages;
}

/** Formulář s podpisem: bez podpisu se neodešle, druhé odeslání se zablokuje */
export function initTabletSignForm(form) {
	const image = form.querySelector('[data-biometric-signature-image]');
	const required = form.querySelector('[data-tablet-sign-required]');
	const submit = form.querySelector('[type="submit"]');

	form.addEventListener('submit', (event) => {
		if (image && image.value === '') {
			event.preventDefault();
			if (required) {
				required.hidden = false;
			}
			return;
		}
		if (required) {
			required.hidden = true;
		}
		if (submit) {
			// až po sestavení dat formuláře - zamknuté tlačítko by se neodeslalo
			setTimeout(() => {
				submit.disabled = true;
			});
		}
	});
}

export function initTabletSignPage(root = document) {
	const penColor = () => getComputedStyle(document.body).getPropertyValue('--signature-pen-color').trim() || '#000';
	// po podpisu schovat výzvu „Podepište se prosím“ z předchozího pokusu o odeslání
	const onChange = () => root.querySelectorAll('[data-tablet-sign-required]').forEach((message) => {
		message.hidden = true;
	});
	initBiometricSignatures(root, { penColor, onChange });

	const container = root.querySelector('[data-tablet-document]');
	if (container) {
		initTabletDocument(container);
	}
	root.querySelectorAll('form[data-tablet-sign-form]').forEach(initTabletSignForm);
}

export default initTabletSignPage;
