// Push změn ze serveru (Mercure, docs/push.md). Odběr dává server v body[data-fancyadmin-push]
// ({url, topics, token}, BasePresenterTrait::getPushTopics()). Jedno spojení na stránku pro
// všechny topiky; zprávu (JSON s `topic`) dostanou posluchači onPush(). Kdo pushem nahrazuje
// polling, ptá se isPushConnected() a poslouchá onPushState() - bez hubu nebo po výpadku
// spojení stránka polluje dál.
//
// Změnu, kterou udělala tahle stránka, si stránka obnoví sama (např. kalendář po uložení) - push
// o ní by obnovil podruhé (kalendář by dvakrát problikl). Stránka má proto své ID, posílá ho
// s každým AJAXem přes jQuery (hlavička X-Fancyadmin-Push-Client), server ho dá do zprávy
// (`origin`) a zprávy s vlastním ID se přeskočí. Ostatní stránky a záložky je dostanou.

const CLIENT_HEADER = 'X-Fancyadmin-Push-Client';
const clientId = Math.random().toString(36).slice(2) + Date.now().toString(36);

const listeners = [];
const stateListeners = [];
let open = false;
let started = false;

/** Zprávy topiku (přesně nebo jeho podtopiků, „user/5" i „user/5/chat") */
export function onPush(topic, callback) {
	listeners.push({ topic, callback });
}

/** Změna stavu spojení: callback(true) po připojení, callback(false) po výpadku */
export function onPushState(callback) {
	stateListeners.push(callback);
}

export function isPushConnected() {
	return open;
}

function setOpen(value) {
	if (open === value) {
		return;
	}
	open = value;
	stateListeners.forEach((callback) => callback(value));
}

function dispatch(data) {
	if (data.origin === clientId) {
		return;
	}
	listeners
		.filter(({ topic }) => data.topic === topic || data.topic?.startsWith(topic + '/'))
		.forEach(({ callback }) => callback(data));
	document.dispatchEvent(new CustomEvent('fancyadmin:push', { detail: data }));
}

/** Čte stream hubu; po výpadku se připojí znovu (1 s, pak dvojnásobně až 30 s) */
async function read(config) {
	const url = new URL(config.url, window.location.href);
	config.topics.forEach((topic) => url.searchParams.append('match', topic));
	let delay = 1000;

	for (;;) {
		try {
			const response = await fetch(url, {
				headers: { Authorization: 'Bearer ' + config.token, Accept: 'text/event-stream' },
				cache: 'no-store',
			});
			// Neplatný nebo prošlý token - nový přinese až další načtení stránky, do té doby polling
			if (response.status === 401 || response.status === 403) {
				setOpen(false);
				return;
			}
			if (!response.ok || !response.body) {
				throw new Error('push');
			}
			setOpen(true);
			delay = 1000;

			const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
			let buffer = '';
			for (;;) {
				const { value, done } = await reader.read();
				if (done) {
					break;
				}
				buffer = (buffer + value).replace(/\r\n?/g, '\n');
				let end;
				while ((end = buffer.indexOf('\n\n')) >= 0) {
					const block = buffer.slice(0, end);
					buffer = buffer.slice(end + 2);
					// Komentář (keep-alive) data nemá
					const data = block.split('\n').filter((line) => line.startsWith('data:')).map((line) => line.slice(5).trimStart()).join('\n');
					if (data) {
						try {
							dispatch(JSON.parse(data));
						} catch {
							// Cizí formát zprávy - přeskočit
						}
					}
				}
			}
		} catch {
			// Výpadek spojení - znovu níž
		}
		setOpen(false);
		await new Promise((resolve) => setTimeout(resolve, delay));
		delay = Math.min(delay * 2, 30000);
	}
}

function start() {
	if (started || typeof TextDecoderStream === 'undefined') {
		return;
	}
	const raw = document.body?.dataset.fancyadminPush;
	if (!raw) {
		return;
	}
	let config;
	try {
		config = JSON.parse(raw);
	} catch {
		return;
	}
	if (!config?.url || !config.topics?.length) {
		return;
	}
	started = true;
	// Jen požadavky na vlastní server - cizí doména by kvůli hlavičce chtěla CORS preflight
	window.jQuery?.ajaxPrefilter((options, originalOptions, xhr) => {
		if (!options.crossDomain) {
			xhr.setRequestHeader(CLIENT_HEADER, clientId);
		}
	});
	read(config);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', start);
} else {
	start();
}
