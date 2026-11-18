// Náhrada pdf.js z import mapy layoutu tabletu (@tablet.latte) - testy dokument nevykreslují
export const GlobalWorkerOptions = {};
export function getDocument() {
	throw new Error('pdf.js se v testech nenačítá.');
}
