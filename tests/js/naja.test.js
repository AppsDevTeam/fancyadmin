// AJAX přes Naja (assets/js/naja.js): které odkazy a formuláře jdou ajaxem a kdy se mění adresa
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

let naja;
let ajax;
let onContentLoaded;
let requests;
let responses;

// Odpověď serveru podle adresy požadavku; bez připravené odpovědi prázdný payload
function respond() {
	window.fetch = async (request) => {
		requests.push(request);
		const path = new URL(request.url).pathname;
		const payload = responses[path] ?? {};
		return new Response(JSON.stringify(payload), { headers: { 'Content-Type': 'application/json' } });
	};
}

function waitForRequests(count = 1) {
	return new Promise((resolve) => {
		let completed = 0;
		const listener = () => {
			if (++completed === count) {
				naja.removeEventListener('complete', listener);
				setTimeout(resolve);
			}
		};
		naja.addEventListener('complete', listener);
	});
}

function click(selector) {
	document.querySelector(selector).click();
}

beforeAll(async () => {
	document.body.innerHTML = '<div id="snippet--content"></div>';
	await import('../../assets/js/_jquery-global.js');
	({ default: naja, ajax, onContentLoaded } = await import('../../assets/js/naja.js'));
	respond();
});

beforeEach(() => {
	requests = [];
	responses = {};
	window.history.replaceState(window.history.state, '', '/connector');
	document.getElementById('snippet--content').innerHTML = `
		<a id="link" href="/connector/files">Soubory</a>
		<a id="same" href="/connector?do=grid-page&amp;_sec=abc">Stejná stránka</a>
		<a id="panel" href="/connector?patientId=5&amp;do=patientPanel&amp;_sec=abc">Boční panel</a>
		<a id="target" href="/connector/files" target="_blank">Nové okno</a>
		<a id="noajax" class="noajax" href="/connector/files">Bez ajaxu</a>
		<a id="hash" href="#top">Kotva</a>
		<a id="external" href="https://example.com/">Jiný web</a>
		<a id="prevented" href="/connector/files">Obslouží JS</a>
		<form id="post" method="post" action="/connector/save"><button id="save">Uložit</button></form>
		<form id="get" method="get" action="/connector/search"><input name="q" value="abc"><button id="search">Hledat</button></form>`;
	document.getElementById('prevented').addEventListener('click', (e) => e.preventDefault());
});

describe('odkazy', () => {
	it('relativní odkaz jde ajaxem a zapíše adresu do historie', async () => {
		const done = waitForRequests();
		click('#link');
		await done;

		expect(requests.map((request) => new URL(request.url).pathname)).toEqual(['/connector/files']);
		expect(window.location.pathname).toBe('/connector/files');
	});

	it('odkaz na tutéž stránku jen se signálem nepřidá záznam historie', async () => {
		const length = window.history.length;
		const done = waitForRequests();
		click('#same');
		await done;

		expect(requests).toHaveLength(1);
		expect(window.history.length).toBe(length);
		expect(window.location.pathname + window.location.search).toBe('/connector');
	});

	it('Zpět po odkazu se signálem načte stránku bez signálu', async () => {
		let done = waitForRequests();
		click('#panel');
		await done;
		done = waitForRequests();
		click('#link');
		await done;

		done = waitForRequests();
		window.history.back();
		await done;

		const back = new URL(requests[2].url);
		expect(back.pathname + back.search).toBe('/connector?patientId=5');
		expect(window.location.pathname + window.location.search).toBe('/connector?patientId=5');
	});

	it.each(['#target', '#noajax', '#hash', '#external', '#prevented'])('%s nejde ajaxem', async (selector) => {
		click(selector);
		await new Promise((resolve) => setTimeout(resolve, 10));

		expect(requests).toHaveLength(0);
	});
});

describe('formuláře', () => {
	it('POST adresu nemění', async () => {
		const done = waitForRequests();
		document.getElementById('post').requestSubmit(document.getElementById('save'));
		await done;

		expect(requests[0].method).toBe('POST');
		expect(window.location.pathname).toBe('/connector');
	});

	it('POST s přesměrováním zapíše adresu přesměrování', async () => {
		responses['/connector/save'] = { redirect: '/connector/detail' };
		const done = waitForRequests(2);
		document.getElementById('post').requestSubmit(document.getElementById('save'));
		await done;

		expect(requests.map((request) => new URL(request.url).pathname)).toEqual(['/connector/save', '/connector/detail']);
		expect(window.location.pathname).toBe('/connector/detail');
	});

	it('GET zapíše adresu i s parametry', async () => {
		const done = waitForRequests();
		document.getElementById('get').requestSubmit(document.getElementById('search'));
		await done;

		expect(window.location.pathname + window.location.search).toBe('/connector/search?q=abc');
	});
});

describe('z kódu', () => {
	it('ajax() překreslí snippety a adresu nemění', async () => {
		responses['/connector/grid'] = { snippets: { 'snippet--content': '<p id="redrawn">Nový obsah</p>' } };
		const loaded = [];
		onContentLoaded(($el) => loaded.push($el[0]));

		await ajax('/connector/grid', { page: 2 });

		expect(new URL(requests[0].url).search).toBe('?page=2');
		expect(document.getElementById('redrawn')).not.toBeNull();
		expect(loaded).toEqual([document, document.getElementById('snippet--content')]);
		expect(window.location.pathname).toBe('/connector');
	});
});
