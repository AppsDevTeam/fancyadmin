// Předčítání odpovědí AI asistenta v rychlé liště (tlačítko s reproduktorem v hlavičce panelu).
// Mluví desktopová aplikace ADTalk (adt/adtalk) - lokálně, vlastním hlasem (Piper, Kasandra),
// na každém systému stejně; text ani zvuk počítač neopustí (odpověď obsahuje skutečná jména
// pacientů). Stránka jí pošle text na http://127.0.0.1:41730 - jen z povolených domén
// a s povolením v CSP (connect-src). Data, časy a částky slovy převádí ADTalk.

const ENABLED_KEY = 'fancyadmin-quickbar-ai-speak';
const ADTALK_URL = 'http://127.0.0.1:41730';

// Do řeči jen text odpovědi - tabulky, grafy, kód a tlačítka pod ní se nečtou
const SKIPPED = 'table, pre, canvas, button, details, .ai-chat__output, .ai-chat__chart, .ai-chat__table, .ai-chat__export';
const BLOCKS = 'p, li, h1, h2, h3, h4, h5, h6, blockquote, br';

// Dostupnost ADTalk se ověřuje nejvýš jednou za tu dobu (aplikaci jde kdykoli spustit / ukončit)
const HEALTH_TTL_MS = 15000;
const STATUS_POLL_MS = 600;

let available = null;
let checkedAt = 0;
let speaking = false;
let pollTimer = null;

export function isEnabled() {
	try {
		return localStorage.getItem(ENABLED_KEY) === '1';
	} catch {
		return false;
	}
}

export function setEnabled(enabled) {
	try {
		localStorage.setItem(ENABLED_KEY, enabled ? '1' : '0');
	} catch {
		// Bez localStorage platí volba do konce stránky - stačí, že se teď vypne
	}
	if (!enabled) {
		stop();
	}
}

export function isSpeaking() {
	return speaking;
}

/** Běží ADTalk? (null = ještě nevíme) */
export function isAvailable() {
	return available;
}

/** Ověří, že ADTalk běží a má načtený hlas; výsledek si chvíli pamatuje */
export async function checkAvailable(force = false) {
	if (!force && available !== null && Date.now() - checkedAt < HEALTH_TTL_MS) {
		return available;
	}
	const status = await request('GET', '/health');
	available = status?.ok === true && status.ready === true;
	checkedAt = Date.now();

	return available;
}

export function stop() {
	clearTimeout(pollTimer);
	if (speaking) {
		speaking = false;
		request('POST', '/stop');
	}
}

/**
 * Pošle vykreslenou odpověď ADTalk k přečtení (předchozí čtení přeruší). onChange se volá
 * při začátku a konci čtení. Vrací, jestli ADTalk čte.
 */
export async function speakReply(element, onChange) {
	const text = readableText(element);
	if (text === '') {
		return false;
	}
	clearTimeout(pollTimer);
	const result = await request('POST', '/speak', { text });
	available = result !== null;
	checkedAt = Date.now();
	if (result?.ok !== true) {
		return false;
	}
	speaking = true;
	onChange?.();
	watchUntilDone(onChange);

	return true;
}

/** Konec čtení hlásí ADTalk ve stavu - tlačítko pak přestane být Zastavit */
function watchUntilDone(onChange) {
	pollTimer = setTimeout(async () => {
		const status = await request('GET', '/health');
		if (speaking && status?.speaking === true) {
			watchUntilDone(onChange);
			return;
		}
		speaking = false;
		onChange?.();
	}, STATUS_POLL_MS);
}

async function request(method, path, body = null) {
	try {
		const response = await fetch(ADTALK_URL + path, {
			method,
			headers: body !== null ? { 'Content-Type': 'application/json' } : {},
			body: body !== null ? JSON.stringify(body) : undefined,
			// Neodpovídá-li ADTalk hned, neběží - čtení nesmí čekat
			signal: AbortSignal.timeout(2000),
		});

		return response.ok ? await response.json() : null;
	} catch {
		return null;
	}
}

function readableText(element) {
	const bubble = element?.querySelector?.('.ai-chat__bubble--ai');
	if (!bubble) {
		return '';
	}
	const clone = bubble.cloneNode(true);
	clone.querySelectorAll(SKIPPED).forEach((node) => node.remove());
	// Konec bloku = konec věty, jinak by se odstavce a položky seznamu slily
	clone.querySelectorAll(BLOCKS).forEach((node) => node.append('\n'));

	return clone.textContent
		.split('\n')
		.map((line) => line.replace(/\s+/g, ' ').trim())
		.filter((line) => line !== '')
		.map((line) => (/[.!?:;…]$/.test(line) ? line : line + '.'))
		.join(' ');
}
