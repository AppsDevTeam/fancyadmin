//
// Stránka s podpisem dokumentů na tabletu (TabletDevicePresenterTrait::actionSign, docs/tablets.md).
//
// - [data-tablet-sign]: stránka, texty v JSON (fcadmin.tablets.sign.*).
// - [data-tablet-sign-nav="<klíč>"]: navigace mezi dokumenty (víc dokumentů), s náhledem první
//   stránky a stavem podpisu.
// - [data-tablet-sign-document="<klíč>"]: dokument - náhled PDF ([data-tablet-document="<url>"],
//   všechny stránky pod sebou, pdf.js) a místa pro podpis ([data-tablet-sign-places], JSON
//   TabletSignPlace::toArray()). Místa označená v PDF se zvýrazní přímo v náhledu, místo
//   dokumentu bez označených míst je pod náhledem.
// - [data-tablet-sign-modal]: okno s plátnem (adt/biometric-signature). Klepnutí do místa ho
//   otevře: podepsat, nebo potvrdit předchozí podpis (nakreslený podpis jde vložit do dalších
//   míst, každé se ale musí potvrdit), případně se podepsat znovu - nový podpis platí jen pro
//   místo, do kterého se klepnulo.
// - form[data-tablet-sign-form]: odešle se, až jsou podepsaná všechna místa všech dokumentů
//   (TabletSignSubmission), po odeslání se tlačítko zamkne.
//
// Stránka je samostatná, bez bundlu projektu: `pdfjs`, `signature_pad` a
// `@biometric-signature/…` jsou přibalené ve fancyadminu a jejich adresy dává import mapa
// v layoutu tabletu (@tablet.latte).
//
import * as pdfjsLib from 'pdfjs';
import { attachBiometricSignature } from '@biometric-signature/biometric-signature.js';

/** Stupně přiblížení dokumentu (1 = šířka plochy) */
const ZOOM_LEVELS = [1, 1.25, 1.5, 2, 2.5];

const PEN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h16"/><path d="M14.5 4.5l3 3L9 16H6v-3z"/></svg>';
const CHECK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

/**
 * Stav podpisu stránky: nakreslené podpisy a které místo je podepsané kterým z nich.
 * Bez DOM - stránka podle něj jen vykresluje.
 *
 * Předchozí podpis se nabízí jen do míst stejného podepisujícího (`signer`, jméno
 * z TabletSignDocument::$signerName) - když žádost nese dokumenty pro víc lidí, podpis jednoho
 * se druhému nenabídne. Stav žije jen v paměti stránky jedné žádosti.
 *
 * @param {Array<{id: string, document: string, signer?: string}>} places všechna místa všech dokumentů v pořadí stránky
 */
export function createSignState(places) {
	/** @type {Map<string, {image: string, biometrics: string}>} */
	const signatures = new Map();
	/** @type {Map<string, string>} místo => podpis */
	const placements = new Map();
	/** @type {Map<string, string>} podepisující => jeho poslední nakreslený podpis */
	const last = new Map();
	let counter = 0;

	const signerOf = (placeId) => places.find((place) => place.id === placeId)?.signer || '';

	const signedIn = (list) => list.filter((place) => placements.has(place.id)).length;

	return {
		places,

		/** Nově nakreslený podpis do místa - platí jen pro ně, další místa ho můžou převzít */
		sign(placeId, signature) {
			const id = `s${++counter}`;
			signatures.set(id, { image: signature.image, biometrics: signature.biometrics });
			placements.set(placeId, id);
			last.set(signerOf(placeId), id);
			return id;
		},

		/** Předchozí podpis téhož podepisujícího potvrzený do dalšího místa */
		reuse(placeId) {
			const id = last.get(signerOf(placeId));
			if (id === undefined) {
				throw new Error('Není co převzít - podepisující místa se zatím nepodepsal.');
			}
			placements.set(placeId, id);
			return id;
		},

		/** Podpis místa, null = nepodepsané */
		get(placeId) {
			const id = placements.get(placeId);
			return id === undefined ? null : signatures.get(id);
		},

		/** Poslední podpis podepisujícího místa (nabídne se do něj), null = zatím žádný */
		getLast(placeId) {
			const id = last.get(signerOf(placeId));
			return id === undefined ? null : signatures.get(id);
		},

		isSigned(placeId) {
			return placements.has(placeId);
		},

		/** První nepodepsané místo - od místa za `afterId` a pak znovu od začátku */
		nextUnsigned(afterId = null) {
			const start = afterId === null ? 0 : places.findIndex((place) => place.id === afterId) + 1;
			const ordered = [...places.slice(start), ...places.slice(0, start)];
			return ordered.find((place) => !placements.has(place.id)) || null;
		},

		/** @returns {{signed: number, total: number}} */
		progress(documentKey = null) {
			const list = documentKey === null ? places : places.filter((place) => place.document === documentKey);
			return { signed: signedIn(list), total: list.length };
		},

		isComplete() {
			return places.length > 0 && signedIn(places) === places.length;
		},

		/** Obsah formuláře (TabletSignSubmission) - jen podpisy, které v některém místě zůstaly */
		toJSON() {
			const used = {};
			const placed = {};
			places.forEach((place) => {
				const id = placements.get(place.id);
				if (id !== undefined) {
					placed[place.id] = id;
					const { image, biometrics } = signatures.get(id);
					used[id] = { image, biometrics };
				}
			});
			return { signatures: used, placements: placed };
		},
	};
}

const format = (text, params) => Object.entries(params).reduce(
	(result, [name, value]) => result.split(`%${name}%`).join(String(value)),
	text || '',
);

/** Jeden worker pdf.js pro všechny dokumenty stránky - každý další by znovu načítal ~1 MB skriptu */
let sharedWorker = null;

/** PDF dokumentu - načte se jednou pro náhled v navigaci i celý dokument */
function loadPdf(container) {
	if (!container.pdfPromise) {
		pdfjsLib.GlobalWorkerOptions.workerSrc = container.dataset.pdfWorker;
		sharedWorker ??= new pdfjsLib.PDFWorker();
		container.pdfPromise = pdfjsLib.getDocument({ url: container.dataset.tabletDocument, worker: sharedWorker }).promise;
	}
	return container.pdfPromise;
}

/** Největší plátno stránky v pixelech - víc iPad nevykreslí (a dlouhý dokument by nevešel do paměti) */
const MAX_PAGE_PIXELS = 8_000_000;

/**
 * Vykreslí stránku do plátna v rozlišení, ve kterém se zobrazí (šířku dává CSS, přiblížení
 * ji zvětší), nejvýš MAX_PAGE_PIXELS. Probíhající vykreslení stejné stránky zruší.
 */
async function renderPage(entry) {
	entry.task?.cancel();
	// ostrost podle displeje tabletu, strop kvůli paměti u dlouhých dokumentů
	const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
	const size = entry.page.getViewport({ scale: 1 });
	let scale = (entry.wrapper.clientWidth || 800) / size.width * pixelRatio;
	scale = Math.min(scale, Math.sqrt(MAX_PAGE_PIXELS / (size.width * size.height)));
	const viewport = entry.page.getViewport({ scale });
	if (entry.canvas.width === Math.floor(viewport.width)) {
		return;
	}

	// nové plátno se vymění až vykreslené - stránka mezitím nezbělá
	const canvas = document.createElement('canvas');
	canvas.width = viewport.width;
	canvas.height = viewport.height;
	entry.task = entry.page.render({ canvasContext: canvas.getContext('2d'), viewport });
	try {
		await entry.task.promise;
	} catch {
		return; // zrušeno novějším vykreslením
	}
	entry.task = null;
	entry.canvas.replaceWith(canvas);
	entry.canvas = canvas;
}

/**
 * Vykreslí PDF do kontejneru po stránkách pod sebou a na každou stránku přidá místa pro podpis
 * (`renderPlace(place, page)` vrátí prvek místa). Stránky přibývají postupně shora, tablet tak
 * nevykresluje celý dokument najednou. Po přiblížení je překreslí rerenderTabletDocument().
 *
 * @returns {Promise<number>} počet stránek, 0 = dokument se nepodařilo načíst
 */
export async function initTabletDocument(container, places = [], renderPlace = null) {
	let pdf;
	try {
		pdf = await loadPdf(container);
	} catch (e) {
		console.error(e);
		container.dataset.tabletDocumentState = 'error';
		return 0;
	}

	container.querySelector('[data-tablet-document-loading]')?.remove();
	container.tabletPages = [];
	for (let number = 1; number <= pdf.numPages; number++) {
		const page = await pdf.getPage(number);
		const wrapper = document.createElement('div');
		wrapper.className = 'tablet-document-page';
		const canvas = document.createElement('canvas');
		wrapper.appendChild(canvas);
		container.appendChild(wrapper);

		const size = page.getViewport({ scale: 1 });
		wrapper.style.aspectRatio = `${size.width} / ${size.height}`;

		// místa v bodech od levého horního rohu stránky (SignatureBox) - v procentech stránky
		if (renderPlace) {
			places.filter((place) => place.page === number).forEach((place) => {
				const element = renderPlace(place);
				element.style.left = `${place.x / size.width * 100}%`;
				element.style.top = `${place.y / size.height * 100}%`;
				element.style.width = `${place.width / size.width * 100}%`;
				element.style.height = `${place.height / size.height * 100}%`;
				wrapper.appendChild(element);
			});
		}

		const entry = { page, wrapper, canvas, task: null };
		container.tabletPages.push(entry);
		await renderPage(entry);
	}
	container.dataset.tabletDocumentState = 'ready';

	return pdf.numPages;
}

/** Překreslí vykreslené stránky dokumentu v nové velikosti (přiblížení) */
export function rerenderTabletDocument(container) {
	(container.tabletPages || []).forEach((entry) => renderPage(entry));
}

/** Náhled první stránky dokumentu do navigace */
async function renderThumbnail(container, target) {
	try {
		const pdf = await loadPdf(container);
		const page = await pdf.getPage(1);
		const size = page.getViewport({ scale: 1 });
		const viewport = page.getViewport({ scale: 120 / size.width });
		const canvas = document.createElement('canvas');
		canvas.width = viewport.width;
		canvas.height = viewport.height;
		await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
		target.replaceChildren(canvas);
	} catch (e) {
		console.error(e);
	}
}

/**
 * Obrázek podpisu oříznutý na samotný tah - jen k zobrazení v místě a v okně (plátno je velké
 * a tah v něm malý). Odesílá se původní obrázek, z něj se počítá otisk; do PDF ho ořízne
 * knihovna (SignatureImage::fit()).
 *
 * @returns {Promise<string>} data URI, při chybě původní obrázek
 */
export function trimSignature(dataUri, padding = 8) {
	return new Promise((resolve) => {
		const image = new Image();
		image.onload = () => {
			try {
				const canvas = document.createElement('canvas');
				canvas.width = image.naturalWidth;
				canvas.height = image.naturalHeight;
				const context = canvas.getContext('2d');
				context.drawImage(image, 0, 0);
				const { data, width, height } = context.getImageData(0, 0, canvas.width, canvas.height);
				let top = height, left = width, right = -1, bottom = -1;
				for (let y = 0; y < height; y++) {
					for (let x = 0; x < width; x++) {
						if (data[(y * width + x) * 4 + 3] > 0) {
							top = Math.min(top, y);
							bottom = Math.max(bottom, y);
							left = Math.min(left, x);
							right = Math.max(right, x);
						}
					}
				}
				if (right < 0) {
					resolve(dataUri);
					return;
				}
				left = Math.max(0, left - padding);
				top = Math.max(0, top - padding);
				const cropWidth = Math.min(width, right + padding + 1) - left;
				const cropHeight = Math.min(height, bottom + padding + 1) - top;
				const cropped = document.createElement('canvas');
				cropped.width = cropWidth;
				cropped.height = cropHeight;
				cropped.getContext('2d').drawImage(canvas, left, top, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
				resolve(cropped.toDataURL('image/png'));
			} catch {
				resolve(dataUri);
			}
		};
		image.onerror = () => resolve(dataUri);
		image.src = dataUri;
	});
}

/** Podpis k zobrazení - ořízne se jednou a pamatuje si to */
const displayImage = (signature) => (signature.display ??= trimSignature(signature.image));

/** Okno s plátnem: podepsat, potvrdit předchozí podpis, nebo se podepsat znovu */
function createSignModal(dialog, texts, penColor) {
	const canvas = dialog.querySelector('[data-tablet-sign-canvas]');
	const imageInput = dialog.querySelector('[data-tablet-sign-image]');
	const biometricsInput = dialog.querySelector('[data-tablet-sign-biometrics]');
	const preview = dialog.querySelector('[data-tablet-sign-preview]');
	const title = dialog.querySelector('[data-tablet-sign-modal-title]');
	const hint = dialog.querySelector('[data-tablet-sign-modal-hint]');
	const caption = dialog.querySelector('[data-tablet-sign-caption]');
	const use = dialog.querySelector('[data-tablet-sign-use]');

	let mode = 'draw';
	let resolve = null;

	const updateUse = () => {
		use.disabled = mode === 'signed' || (mode === 'draw' && imageInput.value === '');
	};
	const pad = attachBiometricSignature(canvas, { imageInput, biometricsInput, penColor, onChange: updateUse });

	const setMode = (next) => {
		mode = next;
		dialog.dataset.mode = next;
		hint.textContent = { draw: texts.hintDraw, reuse: texts.hintReuse, signed: texts.hintSigned }[next];
		if (next === 'draw') {
			pad.clear();
		}
		updateUse();
	};

	const finish = (result) => {
		if (dialog.open) {
			dialog.close();
		}
		if (resolve) {
			const done = resolve;
			resolve = null;
			done(result);
		}
	};

	dialog.querySelector('[data-tablet-sign-restart]').addEventListener('click', () => pad.clear());
	dialog.querySelector('[data-tablet-sign-redraw]').addEventListener('click', () => setMode('draw'));
	dialog.querySelectorAll('[data-tablet-sign-cancel]').forEach((button) => button.addEventListener('click', () => finish(null)));
	// Esc
	dialog.addEventListener('cancel', (event) => {
		event.preventDefault();
		finish(null);
	});
	use.addEventListener('click', async () => {
		if (mode === 'reuse') {
			finish({ reuse: true });
			return;
		}
		if (pad.isEmpty()) {
			return;
		}
		// biometrika se zapisuje až v ticku po zvednutí pera (attachBiometricSignature)
		await new Promise((done) => setTimeout(done));
		if (imageInput.value === '' || biometricsInput.value === '') {
			return;
		}
		finish({ image: imageInput.value, biometrics: biometricsInput.value });
	});

	return {
		/**
		 * @param {{signer: string, current: {image: string}|null, last: {image: string}|null}} options
		 * @returns {Promise<null|{reuse: true}|{image: string, biometrics: string}>} null = zpět
		 */
		open({ signer, current, last }) {
			title.textContent = signer ? `${texts.modalTitle} – ${signer}` : texts.modalTitle;
			caption.textContent = signer ? `${texts.caption} (${signer})` : texts.caption;
			const shown = current || last;
			preview.removeAttribute('src');
			if (shown) {
				displayImage(shown).then((src) => {
					preview.src = src;
				});
			}
			setMode(current ? 'signed' : (last ? 'reuse' : 'draw'));
			dialog.showModal();
			// zaostření na dialog, ne na zavírací tlačítko (ukázalo by kroužek zaostření)
			dialog.focus({ preventScroll: true });

			return new Promise((done) => {
				resolve = done;
			});
		},
	};
}

/** Místo pro podpis: zvýrazněné okénko, po podpisu obrázek podpisu */
function renderPlaceContent(element, place, signature, texts) {
	element.classList.toggle('is-signed', signature !== null);
	if (signature) {
		element.innerHTML = '<img class="tablet-sign-place-image" alt=""><span class="tablet-sign-place-check"></span>';
		displayImage(signature).then((src) => {
			element.querySelector('img').src = src;
		});
		element.querySelector('.tablet-sign-place-check').innerHTML = CHECK_ICON;
		element.setAttribute('aria-label', `${texts.signed}${place.signer ? ` – ${place.signer}` : ''}`);
		return;
	}
	element.innerHTML = '<span class="tablet-sign-place-text"><strong></strong><span></span></span><span class="tablet-sign-place-icon"></span>';
	element.querySelector('strong').textContent = texts.signHere;
	element.querySelector('.tablet-sign-place-text span').textContent = place.signer || '';
	element.querySelector('.tablet-sign-place-icon').innerHTML = PEN_ICON;
	element.setAttribute('aria-label', `${texts.signHere}${place.signer ? ` – ${place.signer}` : ''}`);
}

export function initTabletSignPage(root = document) {
	const page = root.querySelector('[data-tablet-sign]');
	const form = root.querySelector('form[data-tablet-sign-form]');
	const dialog = root.querySelector('[data-tablet-sign-modal]');
	if (!page || !form || !dialog) {
		return null;
	}

	const texts = JSON.parse(page.dataset.tabletSign || '{}');
	const penColor = () => getComputedStyle(document.body).getPropertyValue('--signature-pen-color').trim() || '#000';
	const sections = [...page.querySelectorAll('[data-tablet-sign-document]')];
	const navItems = [...page.querySelectorAll('[data-tablet-sign-nav]')];

	const places = sections.flatMap((section) => {
		const signer = section.dataset.tabletSignSigner || '';
		return JSON.parse(section.dataset.tabletSignPlaces || '[]').map((place) => ({ ...place, signer }));
	});
	const state = createSignState(places);
	const modal = createSignModal(dialog, texts, penColor);
	/** @type {Map<string, HTMLElement>} */
	const placeElements = new Map();

	const input = form.querySelector('[data-tablet-sign-input]');
	const progress = form.querySelector('[data-tablet-sign-progress]');
	const next = form.querySelector('[data-tablet-sign-next]');
	const submit = form.querySelector('[data-tablet-sign-submit]');
	const required = form.querySelector('[data-tablet-sign-required]');

	const update = () => {
		const total = state.progress();
		progress.textContent = format(texts.progress, total);
		// nepodepsaná místa: tlačítko jen vypadá neaktivně, klepnutí řekne proč a ukáže další místo
		submit.classList.toggle('is-incomplete', !state.isComplete());
		next.hidden = state.isComplete();
		if (state.isComplete() && required) {
			required.hidden = true;
		}
		navItems.forEach((item) => {
			const documentProgress = state.progress(item.dataset.tabletSignNav);
			const done = documentProgress.total > 0 && documentProgress.signed === documentProgress.total;
			item.classList.toggle('is-done', done);
			item.querySelector('[data-tablet-sign-nav-state]').textContent = done ? texts.documentDone : format(texts.documentProgress, documentProgress);
		});
	};

	/** @type {Map<string, Promise>} vykreslení dokumentu (náhledy navigace čekají na první) */
	const rendered = new Map();
	const showDocument = (key, { scroll = true } = {}) => {
		sections.forEach((section) => {
			section.hidden = section.dataset.tabletSignDocument !== key;
		});
		navItems.forEach((item) => item.setAttribute('aria-current', item.dataset.tabletSignNav === key ? 'true' : 'false'));
		if (!rendered.has(key)) {
			rendered.set(key, renderDocument(sections.find((section) => section.dataset.tabletSignDocument === key)));
		}
		if (scroll) {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}
	};

	const openPlace = async (place) => {
		const result = await modal.open({
			signer: place.signer,
			current: state.get(place.id),
			last: state.getLast(place.id),
		});
		if (result === null) {
			return;
		}
		if (result.reuse) {
			state.reuse(place.id);
		} else {
			state.sign(place.id, result);
		}
		renderPlaceContent(placeElements.get(place.id), place, state.get(place.id), texts);
		update();
	};

	const createPlaceElement = (place) => {
		const element = document.createElement('button');
		element.type = 'button';
		element.className = 'tablet-sign-place';
		element.dataset.tabletSignPlace = place.id;
		renderPlaceContent(element, place, state.get(place.id), texts);
		element.addEventListener('click', () => openPlace(place));
		placeElements.set(place.id, element);
		return element;
	};

	function renderDocument(section) {
		const key = section.dataset.tabletSignDocument;
		const documentPlaces = places.filter((place) => place.document === key);
		const standalone = section.querySelector('[data-tablet-sign-standalone]');
		documentPlaces.filter((place) => place.box === null).forEach((place) => {
			standalone.appendChild(createPlaceElement(place));
		});

		const container = section.querySelector('[data-tablet-document]');
		if (!container) {
			return Promise.resolve();
		}
		return initTabletDocument(container, documentPlaces.filter((place) => place.box !== null), createPlaceElement).then((pages) => {
			if (pages === 0) {
				container.replaceChildren(Object.assign(document.createElement('p'), {
					className: 'tablet-document-error',
					textContent: texts.loadFailed,
				}));
			}
		});
	}

	navItems.forEach((item) => {
		item.addEventListener('click', () => showDocument(item.dataset.tabletSignNav));
	});
	// náhledy v navigaci až po prvním dokumentu - nebrzdí to, na co se podepisující dívá
	const renderThumbnails = async () => {
		for (const item of navItems) {
			const container = sections.find((section) => section.dataset.tabletSignDocument === item.dataset.tabletSignNav)?.querySelector('[data-tablet-document]');
			const thumb = item.querySelector('[data-tablet-sign-thumb]');
			if (container && thumb) {
				await renderThumbnail(container, thumb);
			}
		}
	};

	// další nepodepsané místo: přepne dokument a místo ukáže (místa v PDF vznikají s vykreslením)
	let lastFocused = null;
	next.addEventListener('click', () => {
		const place = state.nextUnsigned(lastFocused);
		if (!place) {
			return;
		}
		lastFocused = place.id;
		showDocument(place.document, { scroll: false });
		const reveal = (attempt = 0) => {
			const element = placeElements.get(place.id);
			if (!element || !element.isConnected) {
				if (attempt < 50) {
					setTimeout(() => reveal(attempt + 1), 100);
				}
				return;
			}
			element.scrollIntoView({ behavior: 'smooth', block: 'center' });
			element.classList.remove('is-pulsing');
			void element.offsetWidth;
			element.classList.add('is-pulsing');
		};
		reveal();
	});

	// přiblížení dokumentu: stránky jsou širší než plocha (vodorovně se posouvají), po chvíli se
	// překreslí ostře; místo uprostřed obrazovky zůstane uprostřed
	const zoomValue = form.querySelector('.tablet-sign-zoom-value');
	const documents = page.querySelector('.tablet-sign-documents');
	let zoomIndex = 0;
	let zoomTimer = null;
	const setZoom = (index) => {
		const next = Math.max(0, Math.min(ZOOM_LEVELS.length - 1, index));
		if (next === zoomIndex || !documents) {
			return;
		}
		const section = sections.find((item) => !item.hidden);
		const scroller = section?.querySelector('[data-tablet-document]');
		const ratio = ZOOM_LEVELS[next] / ZOOM_LEVELS[zoomIndex];
		const top = section ? section.getBoundingClientRect().top + window.scrollY : 0;
		const centerY = window.scrollY + window.innerHeight / 2 - top;
		const centerX = scroller ? scroller.scrollLeft + scroller.clientWidth / 2 : 0;

		zoomIndex = next;
		documents.style.setProperty('--tablet-zoom', String(ZOOM_LEVELS[next]));
		page.classList.toggle('is-zoomed', next > 0);
		zoomValue.textContent = `${Math.round(ZOOM_LEVELS[next] * 100)} %`;
		form.querySelector('[data-tablet-sign-zoom="-1"]').disabled = next === 0;
		form.querySelector('[data-tablet-sign-zoom="1"]').disabled = next === ZOOM_LEVELS.length - 1;

		window.scrollTo({ top: top + centerY * ratio - window.innerHeight / 2 });
		if (scroller) {
			scroller.scrollLeft = centerX * ratio - scroller.clientWidth / 2;
		}
		rerenderLater();
	};
	const rerenderLater = () => {
		clearTimeout(zoomTimer);
		zoomTimer = setTimeout(() => sections.forEach((item) => {
			const container = item.querySelector('[data-tablet-document]');
			if (container) {
				rerenderTabletDocument(container);
			}
		}), 250);
	};
	// otočení tabletu, změna velikosti okna - stránky se překreslí v nové šířce
	window.addEventListener('resize', rerenderLater);
	form.querySelectorAll('[data-tablet-sign-zoom]').forEach((button) => {
		button.addEventListener('click', () => {
			const step = Number(button.dataset.tabletSignZoom);
			setZoom(step === 0 ? 0 : zoomIndex + step);
		});
	});
	form.querySelector('[data-tablet-sign-zoom="-1"]').disabled = true;

	form.addEventListener('submit', (event) => {
		if (!state.isComplete()) {
			event.preventDefault();
			if (required) {
				required.hidden = false;
			}
			next.click();
			return;
		}
		input.value = JSON.stringify(state.toJSON());
		// až po sestavení dat formuláře - zamknuté tlačítko by se neodeslalo
		setTimeout(() => {
			submit.disabled = true;
		});
	});

	const first = sections[0]?.dataset.tabletSignDocument;
	if (first !== undefined) {
		showDocument(first, { scroll: false });
		rendered.get(first).finally(renderThumbnails);
	}
	update();

	return { state };
}

export default initTabletSignPage;
