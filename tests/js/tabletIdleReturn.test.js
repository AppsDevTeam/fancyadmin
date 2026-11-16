// Stránka formuláře na tabletu (assets/js/tablet.js, initTabletIdleReturn): po nečinnosti zpět
// na čekací stránku - i když tablet spal a časovač mezitím stál - a pryč i tehdy, když na tablet
// přišel další formulář (ten otevřený tím server zrušil).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initTabletIdleReturn } from '../../assets/js/tablet.js';

const WAITING_URL = '/klient/tablet';
// dotaz jde na signál takeForm! čekací stránky (adresa z data-tablet-idle-return)
const PEEK_URL = 'https://fancyadmin.test/klient/tablet?do=takeForm';

function signPage({ seconds = 300 } = {}) {
	const element = document.createElement('div');
	element.dataset.tabletIdleReturn = WAITING_URL;
	element.dataset.tabletIdleSeconds = String(seconds);
	document.body.appendChild(element);
	return element;
}

function respond(form) {
	return vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ ok: true, form }) }));
}

function stubServiceWorker() {
	const container = new EventTarget();
	Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: container });
	return container;
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

describe('stránka formuláře na tabletu', () => {
	let replace;

	beforeEach(() => {
		vi.useFakeTimers();
		setVisibility('visible');
		replace = vi.fn();
		Object.defineProperty(window, 'location', { configurable: true, value: { replace, reload: vi.fn() } });
	});

	afterEach(() => {
		document.body.innerHTML = '';
		delete navigator.serviceWorker;
		vi.unstubAllGlobals();
		vi.useRealTimers();
	});

	it('po nečinnosti se vrátí na čekací stránku, dotek čas posune', () => {
		initTabletIdleReturn(signPage());

		vi.advanceTimersByTime(200 * 1000);
		document.dispatchEvent(new Event('pointerdown'));
		vi.advanceTimersByTime(200 * 1000);
		expect(replace).not.toHaveBeenCalled();

		vi.advanceTimersByTime(100 * 1000);
		expect(replace).toHaveBeenCalledOnce();
		expect(replace).toHaveBeenCalledWith(WAITING_URL);
	});

	it('po probuzení tabletu odejde podle hodin, i když časovač stál', () => {
		vi.stubGlobal('fetch', respond(null));
		initTabletIdleReturn(signPage());

		// uspaný tablet: hodiny běží, časovače ne
		setVisibility('hidden');
		vi.setSystemTime(Date.now() + 2 * 60 * 60 * 1000);
		setVisibility('visible');

		expect(replace).toHaveBeenCalledOnce();
		expect(replace).toHaveBeenCalledWith(WAITING_URL);
		// stránka odešla, na další formulář se už neptá
		expect(fetch).not.toHaveBeenCalled();
	});

	it('po push zprávě odejde, když na tablet čeká další formulář', async () => {
		const container = stubServiceWorker();
		vi.stubGlobal('fetch', respond({ url: '/klient/tablet/sign', title: 'Souhlas' }));
		initTabletIdleReturn(signPage());

		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.waitFor(() => expect(replace).toHaveBeenCalledWith(WAITING_URL));

		const [url, options] = fetch.mock.calls[0];
		expect(String(url)).toBe(PEEK_URL);
		expect(options.body.get('peek')).toBe('1');
	});

	it('pozdní push k právě otevřenému formuláři stránku nezavře', async () => {
		const container = stubServiceWorker();
		vi.stubGlobal('fetch', respond(null));
		initTabletIdleReturn(signPage());

		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.waitFor(() => expect(fetch).toHaveBeenCalledOnce());
		await Promise.resolve();

		expect(replace).not.toHaveBeenCalled();
	});

	it('po návratu do popředí se zeptá na další formulář', async () => {
		vi.stubGlobal('fetch', respond({ url: '/klient/tablet/sign', title: 'Objednávka' }));
		initTabletIdleReturn(signPage());

		setVisibility('hidden');
		setVisibility('visible');

		await vi.waitFor(() => expect(replace).toHaveBeenCalledWith(WAITING_URL));
	});

	it('adresa čekací stránky s vlastním dotazem - signál se přidá k němu', async () => {
		const container = stubServiceWorker();
		vi.stubGlobal('fetch', respond(null));
		const element = signPage();
		element.dataset.tabletIdleReturn = '/klient/tablet?lang=sk';
		initTabletIdleReturn(element);

		workerMessage(container, { type: 'fancyadmin-tablet-form' });
		await vi.waitFor(() => expect(fetch).toHaveBeenCalledOnce());

		expect(String(fetch.mock.calls[0][0])).toBe('https://fancyadmin.test/klient/tablet?lang=sk&do=takeForm');
		expect(replace).not.toHaveBeenCalled();
	});
});
