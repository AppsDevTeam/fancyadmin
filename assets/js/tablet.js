//
// Tablet pro vyplnění a podpis formulářů (docs/tablets.md), bez knihoven.
//
// - [data-tablet-device]: čekací stránka (TabletDevicePresenterTrait) - povolení notifikací
//   a přihlášení k odběru Web Push. Formulář, který obsluha pošle, otevře service worker
//   (sw.js.latte): push ho probudí, formulář si vyzvedne u serveru a otevře ho tady.
// - a[data-tablet-return="10"]: stránka po odeslání formuláře - za 10 s zpět na čekací stránku.
// - [data-tablet-idle-return="<url>"]: stránka formuláře na tabletu - po nečinnosti
//   (data-tablet-idle-seconds, výchozí 300) zpět na čekací stránku, ať formulář nezůstane
//   otevřený dalšímu zákazníkovi. Čekací stránka odkaz na formulář zneplatní (TabletService::$onRelease).
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

export async function initTabletDevice(element) {
	const setState = (state) => {
		element.dataset.tabletState = state;
	};

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
	const idle = root.querySelector('[data-tablet-idle-return]');
	if (idle) {
		initTabletIdleReturn(idle);
	}
}

export default initTabletPage;
