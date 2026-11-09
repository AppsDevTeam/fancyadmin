// Ajaxový přechod mezi stránkami přes history ext: zvýraznění menu a záložek přichází ve snippetu
// body ze serveru a JS ho nesmí přepsat; odscrollované boční menu po výměně snippetu zůstane, kde bylo.
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

let $;

// Stránka, jak by ji poslal server: položka menu svítí na všech akcích svého presenteru,
// záložka jen na své adrese
function page(path) {
	const active = href => (href === path ? ' active' : '');
	const presenter = path.split('/')[1];
	const activeMenu = href => (href === '/' + presenter ? ' active' : '');
	return `
		<div class="side-panel-wide">
			<div class="menu-body">
				<a class="item${activeMenu('/connector')}" href="/connector">Konektory</a>
				<a class="item${activeMenu('/configurations')}" href="/configurations">Nastavení</a>
			</div>
		</div>
		<ul class="nav nav-tabs">
			<li class="nav-item"><a class="nav-link${active('/connector')}" href="/connector">Konektory</a></li>
			<li class="nav-item"><a class="nav-link${active('/connector/files')}" href="/connector/files">Nespárované soubory</a></li>
		</ul>`;
}

// Odpověď serveru na ajaxový požadavek: překreslený snippet body stránky z adresy požadavku
function respondWithPage() {
	$.ajaxTransport('+*', options => ({
		send(headers, done) {
			const path = new URL(options.url, window.location.href).pathname;
			const payload = { snippets: { 'snippet--body': page(path) } };
			setTimeout(() => done(200, 'OK', { text: JSON.stringify(payload) }, 'Content-Type: application/json'));
		},
		abort() {},
	}));
}

// jsdom nemá layout a scrollTop vždy vrací 0 - pro test stačí, že si hodnotu pamatuje
function emulateScrollTop() {
	const scrollTops = new WeakMap();
	Object.defineProperty(window.HTMLElement.prototype, 'scrollTop', {
		configurable: true,
		get() {
			return scrollTops.get(this) ?? 0;
		},
		set(value) {
			scrollTops.set(this, value);
		},
	});
}

async function clickAndWait(selector) {
	const done = new Promise(resolve => $(document).one('ajaxComplete', () => setTimeout(resolve)));
	$(selector).get(0).click();
	await done;
}

beforeAll(async () => {
	emulateScrollTop();
	document.body.innerHTML = `<div id="snippet--body">${page('/connector')}</div>`;

	$ = (await import('../../assets/js/_jquery-global.js')).default;
	await import('nette.ajax.js');
	// Stejně jako app.js
	$.nette.init({
		load(rh) {
			$(this.linkSelector).off('click.nette', rh).on('click.nette', rh);
		},
	}, {
		linkSelector: 'a:not([target]):not([href^="http"]):not([href^="//"]):not(.noajax)',
	});
	await import('../../assets/js/history.ajax.js');
	await import('../../assets/js/sideMenu.js');
	respondWithPage();
});

beforeEach(async () => {
	if (window.location.pathname !== '/connector') {
		await clickAndWait('.nav-tabs a[href="/connector"]');
	}
});

describe('ajaxový přechod', () => {
	// Adresa záložky Konektory je prefixem Nespárovaných souborů - odhad podle prefixu
	// cesty rozsvítil obě
	it('nechá active jen záložce, na kterou se kliklo', async () => {
		await clickAndWait('.nav-tabs a[href="/connector/files"]');

		expect(window.location.pathname).toBe('/connector/files');
		expect($('.nav-tabs .nav-link.active').map((i, el) => el.getAttribute('href')).get()).toEqual(['/connector/files']);
		expect($('.menu-body .item.active').map((i, el) => el.getAttribute('href')).get()).toEqual(['/connector']);
	});

	it('vrátí odscrollovanému menu jeho scroll', async () => {
		document.querySelector('.menu-body').scrollTop = 500;

		await clickAndWait('.menu-body a[href="/configurations"]');

		expect(window.location.pathname).toBe('/configurations');
		expect(document.querySelector('.menu-body').scrollTop).toBe(500);
	});
});
