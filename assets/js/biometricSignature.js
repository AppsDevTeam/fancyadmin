// Pole s biometrickým podpisem (BiometricSignatureInput, docs/biometric-signature.md).
//
// Plátno a záznam biometriky jsou z adt/biometric-signature - JS se bere přímo z balíčku
// ve vendor/ (jedna verze s PHP, formát záznamu se nerozejde), projekt k tomu potřebuje
// alias @biometric-signature ve Vite a signature_pad v package.json. Proto to není
// v app.js: projekt bez podpisu by alias neměl a build by spadl. Projekt modul načte sám
// (dynamickým importem jen na stránce s podpisem, vzor tablet.js).
import { initBiometricSignatures } from '@biometric-signature/biometric-signature.js';

const SELECTOR = '[data-biometric-signature]';

// Barva pera: --signature-pen-color, jinak primární barva značky. Plátno CSS proměnnou
// neumí, načte se při napojení.
const penColor = () => {
	const style = getComputedStyle(document.body);

	return style.getPropertyValue('--signature-pen-color').trim()
		|| style.getPropertyValue('--bs-primary').trim()
		|| '#000';
};

/**
 * Napojí podpisy v kontejneru. Už napojené přeskočí, jde tedy volat opakovaně.
 *
 * @param {ParentNode} [root]
 * @param {object} [options] volby attachBiometricSignature() (penColor, padOptions, onChange)
 */
export function initBiometricSignatureFields(root = document, options = {}) {
	return initBiometricSignatures(root, { penColor, ...options });
}

/**
 * Napojí podpisy na stránce hned a po každém AJAX překreslení (nette.ajax, je-li na stránce).
 *
 * @param {object} [options] viz initBiometricSignatureFields()
 */
export function initBiometricSignaturePage(options = {}) {
	const init = () => initBiometricSignatureFields(document, options);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	if (window.jQuery && window.jQuery.nette) {
		window.jQuery.nette.ext('biometricSignature', {
			complete() {
				if (document.querySelector(SELECTOR)) {
					init();
				}
			},
		});
	}
}
