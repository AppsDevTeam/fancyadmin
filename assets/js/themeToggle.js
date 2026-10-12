// Přepínač světlého/tmavého režimu v uživatelském menu. Režim nastavuje už inline
// skript v hlavičce @layout.latte (kvůli probliknutí), tady se jen přepíná a ukládá.
// Bez uložené volby se režim řídí systémem a sleduje i jeho změnu za běhu.

const STORAGE_KEY = 'fancyadmin-theme';
const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

function readStored() {
	try {
		const theme = localStorage.getItem(STORAGE_KEY);
		return theme === 'light' || theme === 'dark' ? theme : null;
	} catch (e) {
		return null;
	}
}

function store(theme) {
	try {
		localStorage.setItem(STORAGE_KEY, theme);
	} catch (e) {}
}

function currentTheme() {
	return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
}

function syncToggles() {
	const dark = currentTheme() === 'dark';
	document.querySelectorAll('[data-fancyadmin-theme-toggle]').forEach((toggle) => {
		toggle.setAttribute('aria-checked', dark ? 'true' : 'false');
		const checkbox = toggle.querySelector('input[type="checkbox"]');
		if (checkbox) {
			checkbox.checked = dark;
		}
	});
}

function applyTheme(theme) {
	document.documentElement.setAttribute('data-bs-theme', theme);
	syncToggles();
	document.dispatchEvent(new CustomEvent('fancyadmin:theme-change', { detail: { theme } }));
}

// Delegace na document - menu se překresluje ajaxem (snippet body). Capture fáze
// a stopPropagation, aby klik nedošel k Bootstrapu a ten dropdown nezavřel.
document.addEventListener('click', (e) => {
	const toggle = e.target.closest('[data-fancyadmin-theme-toggle]');
	if (!toggle) {
		return;
	}
	e.preventDefault();
	e.stopPropagation();
	const theme = currentTheme() === 'dark' ? 'light' : 'dark';
	store(theme);
	applyTheme(theme);
}, true);

if (media) {
	media.addEventListener('change', (e) => {
		if (readStored() === null) {
			applyTheme(e.matches ? 'dark' : 'light');
		}
	});
}

// Jiná záložka změnila volbu
window.addEventListener('storage', (e) => {
	if (e.key === STORAGE_KEY) {
		applyTheme(readStored() ?? (media?.matches ? 'dark' : 'light'));
	}
});

document.addEventListener('DOMContentLoaded', syncToggles);
// Po ajaxovém překreslení menu nastavit stav přepínače znovu
if ($.nette && $.nette.ext('live')) {
	$.nette.ext('live').after(syncToggles);
}
