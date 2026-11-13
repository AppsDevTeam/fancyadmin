// Vyzvednutí formuláře čekací stránkou tabletu (assets/js/tablet.js, docs/tablets.md): stránka
// se na odeslaný formulář zeptá po načtení, po návratu do popředí a když jí to po push zprávě
// řekne service worker - v rozestupu se neptá. Ptá se jen viditelná stránka, otevírá jen odkazy
// na stejný web a notifikaci workeru po vyzvednutí zavře.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initTabletFormPickup } from '../../assets/js/tablet.js';

const TAKE_FORM_URL = '/klient/tablet?do=takeForm';
const FORM = { url: '/klient/formular/123', title: 'Souhlas' };

function waitingPage() {
	const element = document.createElement('div');
	element.dataset.takeFormUrl = TAKE_FORM_URL;
	element.dataset.swScope = '/klient/tablet';
	document.body.appendChild(element);
	return element;
}

// Odpovědi serveru na takeForm! v pořadí dotazů; poslední se opakuje
function respond(...forms) {
	let call = 0;
	return vi.fn(async () => {
		const form = forms[Math.min(call++, forms.length - 1)];
		return { ok: true, status: 200, json: async () => ({ ok: true, form }) };
	});
}

// Service worker čekací stránky: zprávy stránce a notifikace, které po push zprávě ukázal
function stubServiceWorker(notifications = []) {
	const container = new EventTarget();
	const getNotifications = vi.fn(async () => notifications);
	container.getRegistration = vi.fn(async () => ({ getNotifications }));
	Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: container });
	return { container, getNotifications };
}

function workerMessage(container, data) {
	const event = new Event('message');
	event.data = data;
	container.dispatchEvent(event);
}

function setVisibility(state) {
	Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
	document.dispatchEvent(new Event('visibilitychange'));
}

describe('vyzvednutí formuláře čekací stránkou tabletu', () => {
	let pickup;

	beforeEach(() => {
		vi.useFakeTimers();
		setVisibility('visible');
	});

	afterEach(() => {
		pickup?.stop();
		pickup = null;
		document.body.innerHTML = '';
		delete navigator.serviceWorker;
		vi.unstubAllGlobals();
		vi.useRealTimers();
	});

	it('po načtení stránky se zeptá a čekající formulář otevře', async () => {
		const fetch = respond(FORM);
		vi.stubGlobal('fetch', fetch);
		const navigate = vi.fn();

		pickup = initTabletFormPickup(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);

		expect(fetch).toHaveBeenCalledTimes(1);
		const [url, options] = fetch.mock.calls[0];
		expect(url).toBe(TAKE_FORM_URL);
		expect(options.method).toBe('POST');
		expect(options.credentials).toBe('same-origin');
		expect(options.body.get('poll')).toBe('1'); // server podle toho ví, že se stránka ozvala
		expect(navigate).toHaveBeenCalledExactlyOnceWith(FORM.url);
	});

	it('v rozestupu se neptá - bez události žádný další dotaz', async () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);

		pickup = initTabletFormPickup(waitingPage(), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		await vi.advanceTimersByTimeAsync(10 * 60 * 1000);
		expect(fetch).toHaveBeenCalledTimes(1);
	});

	it('po push zprávě se na pokyn service workeru zeptá hned a jeho notifikaci zavře', async () => {
		const fetch = respond(null, FORM);
		vi.stubGlobal('fetch', fetch);
		const notification = { close: vi.fn() };
		const { container, getNotifications } = stubServiceWorker([notification]);
		const navigate = vi.fn();

		pickup = initTabletFormPickup(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		// cizí zpráva nic nespustí
		workerMessage(container, { type: 'neco-jineho' });
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		notification.close.mockClear();
		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(2);
		expect(container.getRegistration).toHaveBeenLastCalledWith('/klient/tablet');
		expect(getNotifications).toHaveBeenLastCalledWith({ tag: 'fancyadmin-tablet-form' });
		expect(notification.close).toHaveBeenCalledTimes(1);
		expect(navigate).toHaveBeenCalledExactlyOnceWith(FORM.url);

		// po otevření formuláře už na zprávy workeru nereaguje
		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(2);
	});

	it('pokyn během běžícího dotazu se neztratí - zeptá se ještě jednou', async () => {
		// první dotaz odešel dřív, než formulář dorazil na server, a odpověď se zdržela
		let answerFirst;
		const fetch = vi.fn()
			.mockImplementationOnce(() => new Promise((resolve) => {
				answerFirst = () => resolve({ ok: true, status: 200, json: async () => ({ ok: true, form: null }) });
			}))
			.mockResolvedValue({ ok: true, status: 200, json: async () => ({ ok: true, form: FORM }) });
		vi.stubGlobal('fetch', fetch);
		const { container } = stubServiceWorker();
		const navigate = vi.fn();

		pickup = initTabletFormPickup(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		answerFirst();
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(2);
		expect(navigate).toHaveBeenCalledExactlyOnceWith(FORM.url);
	});

	it('skrytá stránka se neptá, po návratu do popředí se zeptá hned', async () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);
		const { container } = stubServiceWorker();

		pickup = initTabletFormPickup(waitingPage(), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		setVisibility('hidden');
		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.advanceTimersByTimeAsync(60000);
		expect(fetch).toHaveBeenCalledTimes(1);

		setVisibility('visible');
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(2);
	});

	it('po otevření stránky bez čekajícího formuláře uklidí starou notifikaci, pak už ne', async () => {
		vi.stubGlobal('fetch', respond(null));
		const notification = { close: vi.fn() };
		const { container } = stubServiceWorker([notification]);

		pickup = initTabletFormPickup(waitingPage(), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(notification.close).toHaveBeenCalledTimes(1);

		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.advanceTimersByTimeAsync(0);
		expect(notification.close).toHaveBeenCalledTimes(1);
	});

	it('odkaz na cizí web neotevře', async () => {
		vi.stubGlobal('fetch', respond({ url: 'https://evil.example/formular', title: '' }));
		const navigate = vi.fn();

		pickup = initTabletFormPickup(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(navigate).not.toHaveBeenCalled();
	});

	it('výpadek sítě přečká, formulář vyzvedne při další události', async () => {
		const fetch = vi.fn()
			.mockRejectedValueOnce(new TypeError('Failed to fetch'))
			.mockResolvedValue({ ok: true, status: 200, json: async () => ({ ok: true, form: FORM }) });
		vi.stubGlobal('fetch', fetch);
		const navigate = vi.fn();

		pickup = initTabletFormPickup(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(navigate).not.toHaveBeenCalled();

		setVisibility('hidden');
		setVisibility('visible');
		await vi.advanceTimersByTimeAsync(0);
		expect(navigate).toHaveBeenCalledExactlyOnceWith(FORM.url);
	});

	it('tablet mezitím někdo odpojil - stránka se načte znovu', async () => {
		vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 400, json: async () => ({ ok: false }) })));
		const reload = vi.fn();
		vi.stubGlobal('location', { ...window.location, origin: window.location.origin, reload });

		pickup = initTabletFormPickup(waitingPage(), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(reload).toHaveBeenCalledTimes(1);
	});

	it('bez adresy pro vyzvednutí se nespustí', () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);

		expect(initTabletFormPickup(document.createElement('div'), vi.fn())).toBeNull();
		expect(fetch).not.toHaveBeenCalled();
	});
});
