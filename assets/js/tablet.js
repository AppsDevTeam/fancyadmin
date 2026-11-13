//
// Tablet pro vyplnění a podpis formulářů (docs/tablets.md), bez knihoven.
//
// - [data-tablet-device]: čekací stránka (TabletDevicePresenterTrait) - povolení notifikací
//   a přihlášení k odběru Web Push. Formulář, který obsluha pošle, čeká u serveru a stránka si ho
//   vyzvedne a otevře: při načtení, po návratu do popředí a když jí to po push zprávě řekne
//   service worker (sw.js.latte), který k tomu ukáže notifikaci. V rozestupu se stránka neptá -
//   bez notifikací (nebo když se push ztratí) ji stačí obnovit ([data-tablet-reload]).
// - a[data-tablet-return="10"]: stránka po odeslání formuláře - za 10 s zpět na čekací stránku.
// - [data-tablet-idle-return="<url>"]: stránka formuláře na tabletu - po nečinnosti
//   (data-tablet-idle-seconds, výchozí 300) zpět na čekací stránku, ať formulář nezůstane
//   otevřený dalšímu zákazníkovi. Čekací stránka odkaz na formulář zneplatní (TabletService::$onRelease).
//
// - [data-tablet-install]: nabídka přidání čekací stránky na plochu - tablet ji má používat jako
//   aplikaci (celá obrazovka, na iPadu jen tak jdou povolit notifikace). V aplikaci z plochy se neukáže.
//
// Zpět se jde přes location.replace(), stránka formuláře tak v historii nezůstane.
//

// Veřejný klíč VAPID (base64url) pro PushManager.subscribe()
function decodeKey(base64Url) {
	const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
	return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}

// Odběr jde vytvořit až u aktivního service workeru - při první registraci se teprve instaluje
function waitForActive(registration) {
	if (registration.active) {
		return Promise.resolve(registration);
	}
	const worker = registration.installing || registration.waiting;
	return new Promise((resolve) => {
		worker.addEventListener('statechange', () => {
			if (worker.state === 'activated') {
				resolve(registration);
			}
		});
	});
}

// Stejné hodnoty má sw.js.latte: zpráva workeru "zeptej se hned" a značka jeho notifikace
const PICK_UP_MESSAGE = 'fancyadmin-tablet-form';
const NOTIFICATION_TAG = 'fancyadmin-tablet-form';

// Jen odkaz na stejný web - nic jiného tablet otevírat nemá (stejně jako service worker)
function sameOrigin(url) {
	try {
		return new URL(url, window.location.origin).origin === window.location.origin;
	} catch {
		return false;
	}
}

// Notifikace workeru o formuláři už není potřeba (stránka formulář otevírá, nebo už nic nečeká)
async function closeFormNotifications(scope) {
	try {
		const registration = await navigator.serviceWorker?.getRegistration(scope);
		const notifications = await registration?.getNotifications({ tag: NOTIFICATION_TAG });
		notifications?.forEach((notification) => notification.close());
	} catch {
		// Prohlížeč notifikace nevydá - zůstane viset, klepnutí na ni jen otevře čekací stránku
	}
}

/**
 * Vyzvednutí odeslaného formuláře čekací stránkou: zeptá se serveru (takeForm!, cookie tabletu)
 * a formulář otevře. Ptá se jen při události, ne v rozestupu:
 * - po načtení stránky (i po obnovení - tak formulář dorazí bez notifikací),
 * - po návratu do popředí (tablet se probudil, klepnutí na notifikaci otevřelo aplikaci),
 * - když jí to po push zprávě řekne service worker; jeho notifikaci stránka po vyzvednutí zavře.
 * Ptá se jen viditelná stránka. Formulář vyzvedává jen ona - service worker ho otevřít
 * spolehlivě neumí (iOS po klepnutí na notifikaci otevře aplikaci na čekací stránce).
 *
 * @param navigate otevření formuláře (v testech se podstrkuje)
 * @returns {{ stop: Function }|null} null = stránka nemá adresu pro vyzvednutí
 */
export function initTabletFormPickup(element, navigate = (url) => window.location.assign(url)) {
	const url = element.dataset.takeFormUrl;
	if (!url) {
		return null;
	}

	let busy = false;
	let again = false;
	let stopped = false;
	// první dotaz bez formuláře uklidí notifikaci, na kterou už není co otevřít (formulář propadl)
	let first = true;

	const onVisibilityChange = () => {
		if (document.visibilityState === 'visible') {
			pickUp();
		}
	};

	// Push zpráva: service worker ukázal notifikaci a formulář nechal na stránce
	const onWorkerMessage = (event) => {
		if (event.data?.type === PICK_UP_MESSAGE) {
			pickUp();
		}
	};

	const stop = () => {
		stopped = true;
		document.removeEventListener('visibilitychange', onVisibilityChange);
		navigator.serviceWorker?.removeEventListener('message', onWorkerMessage);
	};

	async function pickUp() {
		if (stopped || document.visibilityState !== 'visible') {
			return;
		}
		if (busy) {
			// Běžící dotaz mohl odejít dřív, než formulář dorazil na server - zeptat se ještě jednou
			again = true;
			return;
		}

		busy = true;
		again = false;
		try {
			const body = new FormData();
			body.append('poll', '1');
			const response = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
			if (response.status === 400) {
				// Tablet mezitím někdo odpojil nebo spároval jinde - stránka to po načtení řekne
				stop();
				window.location.reload();
				return;
			}
			const form = response.ok ? (await response.json()).form : null;
			const open = form && sameOrigin(form.url);
			if (open) {
				stop();
			}
			if (open || (first && response.ok)) {
				first = false;
				await closeFormNotifications(element.dataset.swScope);
			}
			if (open) {
				navigate(form.url);
				return;
			}
		} catch {
			// Bez sítě - formulář se vyzvedne při další události (návrat do popředí, obnovení stránky)
		} finally {
			busy = false;
		}

		if (again) {
			pickUp();
		}
	}

	document.addEventListener('visibilitychange', onVisibilityChange);
	navigator.serviceWorker?.addEventListener('message', onWorkerMessage);
	pickUp();

	return { stop };
}

export async function initTabletDevice(element) {
	const setState = (state) => {
		element.dataset.tabletState = state;
	};

	// Formulář se vyzvedává i bez podpory notifikací - po obnovení stránky
	initTabletFormPickup(element);
	element.querySelector('[data-tablet-reload]')?.addEventListener('click', () => window.location.reload());

	if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
		setState('unsupported');
		return;
	}

	const register = async () => {
		setState('loading');
		try {
			if (!element.dataset.vapidKey) {
				throw new Error('Web Push není na serveru nastavený (klíč VAPID).');
			}
			const registration = await waitForActive(await navigator.serviceWorker.register(element.dataset.swUrl, { scope: element.dataset.swScope }));
			const applicationServerKey = decodeKey(element.dataset.vapidKey);
			let subscription = await registration.pushManager.getSubscription();
			// Odběr s jiným klíčem (klíč serveru se vyměnil) už nedoručí - nahradit
			if (subscription && subscription.options.applicationServerKey
				&& new Uint8Array(subscription.options.applicationServerKey).toString() !== applicationServerKey.toString()) {
				await subscription.unsubscribe();
				subscription = null;
			}
			subscription ??= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey });

			// Adresa odběru se posílá při každém otevření - prohlížeč ji mohl obnovit
			const body = new FormData();
			body.append('endpoint', subscription.endpoint);
			const response = await fetch(element.dataset.registerUrl, { method: 'POST', body, credentials: 'same-origin' });
			setState(response.ok ? 'ready' : 'error');
		} catch (e) {
			console.error(e);
			setState(Notification.permission === 'denied' ? 'denied' : 'error');
		}
	};

	element.querySelector('[data-tablet-enable]')?.addEventListener('click', async () => {
		// Žádost o povolení musí přijít z klepnutí (iPad jinak nic nezobrazí)
		const permission = await Notification.requestPermission();
		if (permission === 'granted') {
			await register();
		} else {
			setState(permission === 'denied' ? 'denied' : 'permission');
		}
	});

	if (Notification.permission === 'granted') {
		await register();
	} else {
		setState(Notification.permission === 'denied' ? 'denied' : 'permission');
	}
}

export function initTabletReturn(link) {
	link.addEventListener('click', (event) => {
		event.preventDefault();
		window.location.replace(link.href);
	});
	setTimeout(() => window.location.replace(link.href), Number(link.dataset.tabletReturn) * 1000);
}

export function initTabletIdleReturn(element) {
	const url = element.dataset.tabletIdleReturn;
	const timeout = Number(element.dataset.tabletIdleSeconds || 300) * 1000;
	let timer;
	const restart = () => {
		clearTimeout(timer);
		timer = setTimeout(() => window.location.replace(url), timeout);
	};
	['pointerdown', 'keydown', 'input', 'scroll', 'touchmove'].forEach((type) => {
		document.addEventListener(type, restart, { passive: true, capture: true });
	});
	restart();

	// Stránka obnovená z mezipaměti historie (tlačítko Zpět) se načte znovu - odkaz už
	// může být neplatný a obsah formuláře se nemá ukázat bez dotazu na server
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			window.location.reload();
		}
	});
}

/**
 * Nabídka přidání na plochu, když stránka běží v prohlížeči (ne jako aplikace z plochy).
 * Stav v data-tablet-install: "prompt" = prohlížeč umí instalaci nabídnout sám (Chrome, Edge,
 * Samsung) - tlačítko [data-tablet-install-button] ji vyvolá; "ios" = návod pro Safari.
 */
export function initTabletInstall(element) {
	const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	if (standalone) {
		return;
	}

	// iPad se od iPadOS 13 hlásí jako Mac - pozná se podle dotykové obrazovky
	if (/iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
		element.dataset.tabletInstall = 'ios';
	}

	let installEvent = null;

	// Prohlížeč stránku uznal za instalovatelnou (manifest, https) - dialog jde vyvolat jen z klepnutí
	window.addEventListener('beforeinstallprompt', (event) => {
		event.preventDefault();
		installEvent = event;
		element.dataset.tabletInstall = 'prompt';
	});

	element.querySelector('[data-tablet-install-button]')?.addEventListener('click', async () => {
		if (!installEvent) {
			return;
		}
		installEvent.prompt();
		const choice = await installEvent.userChoice;
		// událost jde použít jen jednou; po odmítnutí pošle prohlížeč novou, až bude instalaci zase nabízet
		installEvent = null;
		if (choice.outcome === 'accepted') {
			element.dataset.tabletInstall = '';
		}
	});

	window.addEventListener('appinstalled', () => {
		element.dataset.tabletInstall = '';
	});
}

/** Inicializace všech částí tabletu na stránce */
export function initTabletPage(root = document) {
	const device = root.querySelector('[data-tablet-device]');
	if (device) {
		initTabletDevice(device);
	}
	const returnLink = root.querySelector('a[data-tablet-return]');
	if (returnLink) {
		initTabletReturn(returnLink);
	}
	const install = root.querySelector('[data-tablet-install]');
	if (install) {
		initTabletInstall(install);
	}
	const idle = root.querySelector('[data-tablet-idle-return]');
	if (idle) {
		initTabletIdleReturn(idle);
	}
}

export default initTabletPage;
