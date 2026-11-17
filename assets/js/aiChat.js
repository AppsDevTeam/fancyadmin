// AI asistent (adt/ai-chat) v bočním panelu AiPresenteru - volitelné, projekt ho
// importuje sám (README, AI asistent) a doinstaluje adt-ai-chat, marked, dompurify
// a chart.js. Mimo src/UI/**/index.js, aby ho nebalily projekty bez AI.
//
// Widget se připojí na každý [data-adt-fancyadmin-ai-chat] (config v atributu),
// i na ten, který přijde ajaxem ve snippetu panelu. Do URL stránky widget nic nepíše,
// odkaz na konverzaci nabízí lišta (Kopírovat odkaz).

import { AiChat } from 'adt-ai-chat';
import 'adt-ai-chat/ai-chat.css';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import Chart from 'chart.js/auto';
import '../scss/_aiChat.scss';
import { ajax } from './naja';

const SELECTOR = '[data-adt-fancyadmin-ai-chat]';
const GRID_REFRESH_SELECTOR = '[data-adt-fancyadmin-ai-grid-refresh]';
const OPEN_SELECTOR = '[data-adt-fancyadmin-ai-open]';

function mount(root) {
	root.querySelectorAll?.(SELECTOR).forEach((element) => {
		// instance na elementu - rychlá lišta (quickbar.js) přes ni přepíná konverzace, sama adt-ai-chat neimportuje
		element.adtAiChat = AiChat.mount(element, { marked, DOMPurify, Chart }, JSON.parse(element.dataset.adtFancyadminAiChat || '{}'));
		// formulář si odesílá widget sám, ajax (naja.js) ho nesmí převzít
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

document.addEventListener('ai-chat:conversation', (event) => {
	if (event.detail?.created) {
		refreshGrid();
	}
});

new MutationObserver((records) => {
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

// odkaz na konverzaci (?aiConversationGrid-id=ID): otevřít konverzaci v bočním panelu - až po load,
// kdy je Naja inicializovaná
window.addEventListener('load', () => {
	const holder = document.querySelector(OPEN_SELECTOR);
	if (holder) {
		ajax(holder.dataset.adtFancyadminAiOpen);
	}
});
