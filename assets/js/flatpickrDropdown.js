// Vlastní rozbalovací seznam pro <select>y v kalendáři (měsíc, rok, hodiny, minuty).
//
// Proč vlastní, když select umí seznam sám: nativní seznam si prohlížeč kreslí i
// umisťuje po svém a CSS na něj nedosáhne. Styl přes `appearance: base-select` funguje
// jen v Chromiu 135+, jinde je seznam široký jako select (u času, kde je ze selectu
// vidět jen šipka, tedy pár pixelů) a Firefox ho navíc překlápí přes kalendář, aby
// vybraná položka seděla na tlačítku. Panel níž vypadá a umisťuje se všude stejně.
//
// Select zůstává v DOM: drží hodnotu, klávesnici (šipky mění hodnotu bez otevírání)
// i přístupnost. Panel jen zastupuje myší otevření seznamu.

const PANEL_CLASS = 'flatpickr-dropdown';
const OPTION_CLASS = 'flatpickr-dropdown-option';
const GAP = 4;

// Panel je vždy nejvýš jeden — otevření dalšího zavírá předchozí
let openInstance = null;

function closeOpen() {
	openInstance?.close();
}

document.addEventListener('mousedown', (event) => {
	// Vlastní select vynechat: jeho handler běží dřív (bublání od elementu k
	// dokumentu), takže by tenhle zavřel panel ve stejném kliknutí, co ho otevřelo
	if (openInstance && event.target !== openInstance.select && !event.target.closest('.' + PANEL_CLASS)) {
		closeOpen();
	}
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		closeOpen();
	}
});

/**
 * Napojí na select vlastní panel se seznamem.
 *
 * @param {HTMLSelectElement} select
 * @param {HTMLElement} container kam se panel vloží (kalendář, aby se stínem i scrollem patřil k němu)
 * @param {{align?: 'center'|'end', minWidth?: number}} config
 */
export default function attachDropdown(select, container, config = {}) {
	const align = config.align ?? 'center';
	let panel = null;

	const close = () => {
		panel?.remove();
		panel = null;
		if (openInstance?.select === select) {
			openInstance = null;
		}
	};

	const position = () => {
		const containerRect = container.getBoundingClientRect();
		const buttonRect = select.getBoundingClientRect();
		const panelRect = panel.getBoundingClientRect();

		// Pod tlačítko; nad něj jen když se dole nevejde a nahoře ano
		const fitsBelow = buttonRect.bottom + GAP + panelRect.height <= window.innerHeight;
		const fitsAbove = buttonRect.top - GAP - panelRect.height >= 0;
		const top = fitsBelow || !fitsAbove
			? buttonRect.bottom + GAP - containerRect.top
			: buttonRect.top - GAP - panelRect.height - containerRect.top;

		let left = align === 'end'
			? buttonRect.right - panelRect.width
			: buttonRect.left + (buttonRect.width - panelRect.width) / 2;

		// Ať panel nevyteče z obrazovky (pole u kraje okna)
		left = Math.min(Math.max(left, GAP), window.innerWidth - panelRect.width - GAP);

		panel.style.setProperty('--dropdown-top', `${Math.round(top)}px`);
		panel.style.setProperty('--dropdown-left', `${Math.round(left - containerRect.left)}px`);
	};

	const open = () => {
		closeOpen();

		panel = document.createElement('div');
		panel.className = PANEL_CLASS;
		panel.setAttribute('role', 'listbox');
		if (config.minWidth) {
			panel.style.setProperty('--dropdown-min-width', `${config.minWidth}px`);
		}

		let selectedOption = null;
		Array.from(select.options).forEach((option) => {
			const item = document.createElement('div');
			item.className = OPTION_CLASS;
			item.setAttribute('role', 'option');
			item.textContent = option.textContent;
			if (option.value === select.value) {
				item.classList.add('selected');
				item.setAttribute('aria-selected', 'true');
				selectedOption = item;
			}
			item.addEventListener('click', () => {
				select.value = option.value;
				select.dispatchEvent(new Event('change', { bubbles: true }));
				close();
			});
			panel.appendChild(item);
		});

		container.appendChild(panel);
		position();
		// Dlouhé seznamy (hodiny, roky) otevřít u vybrané hodnoty, ne na začátku
		selectedOption?.scrollIntoView({ block: 'center' });

		openInstance = { select, close };
	};

	// Nativní seznam nesmí vyjet; preventDefault na mousedown ho zruší ve všech
	// prohlížečích a klávesnice (šipky, type-ahead) na selectu funguje dál.
	select.addEventListener('mousedown', (event) => {
		event.preventDefault();
		if (panel) {
			close();
		} else {
			open();
		}
	});

	// Po výběru klávesnicí nebo překreslení seznamu nemá smysl držet otevřený panel
	select.addEventListener('change', close);

	return { close };
}
