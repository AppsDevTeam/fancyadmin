// AI asistent (adt/ai-chat) v bočním panelu AiPresenteru - volitelné, projekt ho
// importuje sám (README, sekce 28) a doinstaluje adt-ai-chat, marked, dompurify
// a chart.js. Mimo src/UI/**/index.js, aby ho nebalily projekty bez AI.
//
// Widget se připojí na každý [data-adt-fancyadmin-ai-chat] (config v atributu),
// i na ten, který přijde ajaxem ve snippetu panelu. Otevřená konverzace je v URL
// (config.urlParam), po zavření panelu se z ní zase odebere.

import { AiChat } from 'adt-ai-chat';
import 'adt-ai-chat/ai-chat.css';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import Chart from 'chart.js/auto';
import '../scss/_aiChat.scss';

const SELECTOR = '[data-adt-fancyadmin-ai-chat]';
const GRID_REFRESH_SELECTOR = '[data-adt-fancyadmin-ai-grid-refresh]';
const OPEN_SELECTOR = '[data-adt-fancyadmin-ai-open]';

function ajax(url) {
	if (window.jQuery?.nette?.ajax) {
		window.jQuery.nette.ajax({ url });
	} else {
		window.location.reload();
	}
}

function mount(root) {
	root.querySelectorAll?.(SELECTOR).forEach((element) => {
		AiChat.mount(element, { marked, DOMPurify, Chart }, JSON.parse(element.dataset.adtFancyadminAiChat || '{}'));
		// formulář si odesílá widget sám, nette.ajax ho nesmí převzít
		element.querySelectorAll('form').forEach((form) => form.classList.add('noajax'));
	});
}

// nová konverzace (první zpráva v panelu) se má objevit v gridu
function refreshGrid() {
	const holder = document.querySelector(GRID_REFRESH_SELECTOR);
	if (!holder) {
		return;
	}

	ajax(holder.dataset.adtFancyadminAiGridRefresh);
}

// zavřený panel (nebo jiná konverzace v něm) - sdílený odkaz už neplatí
function unmount(node) {
	const elements = node.matches(SELECTOR) ? [node] : node.querySelectorAll?.(SELECTOR) ?? [];
	elements.forEach((element) => {
		const urlParam = JSON.parse(element.dataset.adtFancyadminAiChat || '{}').urlParam;
		if (!urlParam) {
			return;
		}

		const url = new URL(window.location.href);
		if (url.searchParams.has(urlParam)) {
			url.searchParams.delete(urlParam);
			window.history.replaceState(window.history.state, '', url);
		}
	});
}

document.addEventListener('ai-chat:conversation', (event) => {
	if (event.detail?.created) {
		refreshGrid();
	}
});

new MutationObserver((records) => {
	records.forEach((record) => record.removedNodes.forEach((node) => {
		if (node.nodeType === Node.ELEMENT_NODE) {
			unmount(node);
		}
	}));
	records.forEach((record) => record.addedNodes.forEach((node) => {
		if (node.nodeType === Node.ELEMENT_NODE) {
			if (node.matches(SELECTOR)) {
				mount(node.parentElement);
			} else {
				mount(node);
			}
		}
	}));
}).observe(document.body, { childList: true, subtree: true });

mount(document);

// sdílený odkaz (?conversation=ID): otevřít konverzaci v bočním panelu - až po load,
// kdy je nette.ajax inicializovaný
window.addEventListener('load', () => {
	const holder = document.querySelector(OPEN_SELECTOR);
	// bez nette.ajax ne - záložní reload by se zacyklil
	if (holder && window.jQuery?.nette?.ajax) {
		window.jQuery.nette.ajax({ url: holder.dataset.adtFancyadminAiOpen });
	}
});
