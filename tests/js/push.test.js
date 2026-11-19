// Obnova odběru pushe (assets/js/push.js, docs/push.md): token platí 12 h, pak ho hub odmítne.
// Dřív push.js po 401 skončil natrvalo a stránka otevřená přes noc jen pollovala.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const HUB = 'https://fancyadmin.test/.well-known/mercure';
const REFRESH = '/connector?do=pushSubscription&_sec=abc';

let hubResponses;
let refreshResponses;
let push;

const json = (data, status = 200) => new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } });
const stream = () => new Response(new ReadableStream({ start() {} }), { status: 200, headers: { 'Content-Type': 'text/event-stream' } });
const subscription = (token) => ({ url: '/.well-known/mercure', topics: ['https://fancyadmin.test/calendar'], token });

const hubCalls = () => fetch.mock.calls.filter(([url]) => String(url).startsWith(HUB));
const refreshCalls = () => fetch.mock.calls.filter(([url]) => String(url) === REFRESH);

async function startPush(config) {
	document.body.dataset.fancyadminPush = JSON.stringify(config);
	push = await import('../../assets/js/push.js');
}

// Smyčka push.js je asynchronní - chvíli nechat doběhnout, ať je vidět, že už nic dalšího nevolá
const settle = () => new Promise((resolve) => setTimeout(resolve, 50));

beforeEach(() => {
	vi.resetModules();
	hubResponses = [];
	refreshResponses = [];
	globalThis.fetch = vi.fn((url) => {
		const queue = String(url) === REFRESH ? refreshResponses : hubResponses;
		return Promise.resolve(queue.shift()?.() ?? new Response('', { status: 500 }));
	});
});

afterEach(() => {
	delete document.body.dataset.fancyadminPush;
});

describe('push.js po odmítnutí tokenu', () => {
	it('vyžádá si nový odběr a připojí se s novým tokenem', async () => {
		hubResponses.push(() => new Response('', { status: 401 }), stream);
		refreshResponses.push(() => json(subscription('fresh')));
		await startPush({ ...subscription('expired'), refreshUrl: REFRESH });

		await vi.waitFor(() => expect(push.isPushConnected()).toBe(true));
		expect(hubCalls()).toHaveLength(2);
		expect(hubCalls()[1][1].headers.Authorization).toBe('Bearer fresh');
		expect(new URL(hubCalls()[1][0]).searchParams.getAll('match')).toEqual(['https://fancyadmin.test/calendar']);
		expect(refreshCalls()[0][1].headers.Accept).toBe('application/json');
	});

	it.each([
		['přesměrování na přihlášení (HTML)', () => new Response('<html>', { status: 200, headers: { 'Content-Type': 'text/html' } })],
		['403', () => new Response('', { status: 403 })],
		['404 (stránka zmizela)', () => new Response('', { status: 404 })],
		['null (bez topiků)', () => json(null)],
	])('odpověď %s - zůstane odpojená a dál to nezkouší', async (_, refreshResponse) => {
		hubResponses.push(() => new Response('', { status: 401 }));
		refreshResponses.push(refreshResponse);
		await startPush({ ...subscription('expired'), refreshUrl: REFRESH });

		await vi.waitFor(() => expect(refreshCalls()).toHaveLength(1));
		await settle();
		expect(push.isPushConnected()).toBe(false);
		expect(hubCalls()).toHaveLength(1);
	});

	it('hub odmítne i nový token - konec bez smyčky', async () => {
		hubResponses.push(() => new Response('', { status: 401 }), () => new Response('', { status: 401 }));
		refreshResponses.push(() => json(subscription('fresh')), () => json(subscription('fresh2')));
		await startPush({ ...subscription('expired'), refreshUrl: REFRESH });

		await vi.waitFor(() => expect(hubCalls()).toHaveLength(2));
		await settle();
		expect(fetch).toHaveBeenCalledTimes(3);
		expect(push.isPushConnected()).toBe(false);
	});

	it('stránka bez refreshUrl (starší layout) skončí jako dřív', async () => {
		hubResponses.push(() => new Response('', { status: 401 }));
		await startPush(subscription('expired'));

		await vi.waitFor(() => expect(hubCalls()).toHaveLength(1));
		await settle();
		expect(fetch).toHaveBeenCalledTimes(1);
		expect(push.isPushConnected()).toBe(false);
	});
});
