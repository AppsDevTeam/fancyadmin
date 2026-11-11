// Záložní dotazování čekací stránky tabletu (assets/js/tablet.js, docs/tablets.md): otevřená
// stránka se na odeslaný formulář ptá sama, takže dorazí i bez notifikací. Ptá se jen viditelná
// stránka, s fungujícími notifikacemi řidčeji, a otevírá jen odkazy na stejný web.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initTabletPolling } from '../../assets/js/tablet.js';

const TAKE_FORM_URL = '/klient/tablet?do=takeForm';

function waitingPage({ pollSeconds = '5', state = 'unsupported' } = {}) {
	const element = document.createElement('div');
	element.dataset.takeFormUrl = TAKE_FORM_URL;
	element.dataset.pollSeconds = pollSeconds;
	element.dataset.tabletState = state;
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

function setVisibility(state) {
	Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
	document.dispatchEvent(new Event('visibilitychange'));
}

describe('dotazování čekací stránky tabletu', () => {
	let polling;

	beforeEach(() => {
		vi.useFakeTimers();
		setVisibility('visible');
	});

	afterEach(() => {
		polling?.stop();
		polling = null;
		document.body.innerHTML = '';
		vi.unstubAllGlobals();
		vi.useRealTimers();
	});

	it('zeptá se hned po otevření a pak v rozestupu, formulář otevře', async () => {
		const fetch = respond(null, null, { url: '/klient/formular/123', title: 'Souhlas' });
		vi.stubGlobal('fetch', fetch);
		const navigate = vi.fn();

		polling = initTabletPolling(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);
		const [url, options] = fetch.mock.calls[0];
		expect(url).toBe(TAKE_FORM_URL);
		expect(options.method).toBe('POST');
		expect(options.credentials).toBe('same-origin');
		expect(options.body.get('poll')).toBe('1'); // server podle toho ví, že stránka svítí

		await vi.advanceTimersByTimeAsync(5000);
		expect(fetch).toHaveBeenCalledTimes(2);
		expect(navigate).not.toHaveBeenCalled();

		await vi.advanceTimersByTimeAsync(5000);
		expect(navigate).toHaveBeenCalledExactlyOnceWith('/klient/formular/123');

		// po otevření formuláře se už neptá
		await vi.advanceTimersByTimeAsync(60000);
		expect(fetch).toHaveBeenCalledTimes(3);
	});

	it('s fungujícími notifikacemi se ptá šestkrát řidčeji', async () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);

		polling = initTabletPolling(waitingPage({ state: 'ready' }), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		await vi.advanceTimersByTimeAsync(29000);
		expect(fetch).toHaveBeenCalledTimes(1);
		await vi.advanceTimersByTimeAsync(1000);
		expect(fetch).toHaveBeenCalledTimes(2);
	});

	it('skrytá stránka se neptá, po návratu se zeptá hned', async () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);

		polling = initTabletPolling(waitingPage(), vi.fn());
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(1);

		setVisibility('hidden');
		await vi.advanceTimersByTimeAsync(60000);
		expect(fetch).toHaveBeenCalledTimes(1);

		setVisibility('visible');
		await vi.advanceTimersByTimeAsync(0);
		expect(fetch).toHaveBeenCalledTimes(2);
	});

	it('odkaz na cizí web neotevře', async () => {
		vi.stubGlobal('fetch', respond({ url: 'https://evil.example/formular', title: '' }));
		const navigate = vi.fn();

		polling = initTabletPolling(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(navigate).not.toHaveBeenCalled();
	});

	it('výpadek sítě přečká a zkusí to znovu', async () => {
		const fetch = vi.fn()
			.mockRejectedValueOnce(new TypeError('Failed to fetch'))
			.mockResolvedValue({ ok: true, status: 200, json: async () => ({ ok: true, form: { url: '/klient/formular/7', title: '' } }) });
		vi.stubGlobal('fetch', fetch);
		const navigate = vi.fn();

		polling = initTabletPolling(waitingPage(), navigate);
		await vi.advanceTimersByTimeAsync(0);
		expect(navigate).not.toHaveBeenCalled();

		await vi.advanceTimersByTimeAsync(5000);
		expect(navigate).toHaveBeenCalledExactlyOnceWith('/klient/formular/7');
	});

	it('s rozestupem 0 je dotazování vypnuté', async () => {
		const fetch = respond(null);
		vi.stubGlobal('fetch', fetch);

		expect(initTabletPolling(waitingPage({ pollSeconds: '0' }), vi.fn())).toBeNull();
		await vi.advanceTimersByTimeAsync(60000);
		expect(fetch).not.toHaveBeenCalled();
	});
});
