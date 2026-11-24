import {Modal} from 'bootstrap';

/**
 * Potvrzovaci a informacni dialog (Bootstrap modal) misto nativnich confirm() a alert().
 *
 * Nativni dialog nejde nastylovat, tlacitka nesou texty prohlizece a zablokuje celou
 * stranku. Modal je asynchronni - akce se proto nepta navratovou hodnotou, ale predava
 * callback `onConfirm`. Zavreni Esc nebo klikem vedle je vzdy Zrusit.
 *
 * Vychozi texty tlacitek vykresluje layout do `[data-fcadmin-dialog]` (preklady
 * `fcadmin.dialog.*`), bez nej plati anglicke.
 */

const defaults = () => {
	const el = document.querySelector('[data-fcadmin-dialog]');

	return {
		confirm: el?.dataset.confirm || 'OK',
		cancel: el?.dataset.cancel || 'Cancel',
		close: el?.dataset.close || 'Close',
	};
};

/**
 * @param {{title: ?string, text: ?string, buttons: Array<{label: string, primary: boolean, onClick: ?function(): void}>, onDismiss: ?function(): void}} options
 */
const open = ({title, text, buttons, onDismiss}) => {
	const modalEl = document.createElement('div');
	modalEl.className = 'modal fade fcadmin-dialog';
	modalEl.tabIndex = -1;
	modalEl.setAttribute('role', 'dialog');
	modalEl.setAttribute('aria-modal', 'true');

	const dialog = document.createElement('div');
	dialog.className = 'modal-dialog modal-dialog-centered';
	modalEl.appendChild(dialog);

	const content = document.createElement('div');
	content.className = 'modal-content';
	dialog.appendChild(content);

	if (title) {
		const titleEl = document.createElement('h2');
		titleEl.className = 'fcadmin-dialog__title';
		titleEl.id = 'fcadmin-dialog-title-' + Date.now();
		titleEl.textContent = title;
		modalEl.setAttribute('aria-labelledby', titleEl.id);
		content.appendChild(titleEl);
	}

	if (text) {
		const body = document.createElement('div');
		body.className = 'fcadmin-dialog__text';
		for (const paragraph of String(text).split(/\n\s*\n/)) {
			const p = document.createElement('p');
			p.textContent = paragraph;
			body.appendChild(p);
		}
		content.appendChild(body);
	}

	const footer = document.createElement('div');
	footer.className = 'fcadmin-dialog__buttons';
	content.appendChild(footer);

	const modal = new Modal(modalEl);
	let action = null;

	for (const button of buttons) {
		const buttonEl = document.createElement('button');
		buttonEl.type = 'button';
		buttonEl.className = 'btn ' + (button.primary ? 'btn-primary' : 'btn-secondary');
		buttonEl.textContent = button.label;
		buttonEl.addEventListener('click', () => {
			action = button.onClick ?? null;
			modal.hide();
		});
		footer.appendChild(buttonEl);
	}

	modalEl.addEventListener('shown.bs.modal', () => {
		footer.querySelector('.btn-primary')?.focus();
	});

	// Akce bezi az po zavreni dialogu - kdyby otevrela dalsi dialog nebo prekreslila
	// snippet, nesmi ji rusit odchazejici modal a jeho backdrop.
	modalEl.addEventListener('hidden.bs.modal', () => {
		modal.dispose();
		modalEl.remove();
		(action ?? onDismiss)?.();
	});

	modal.show();
};

/**
 * Potvrzeni akce. Leve tlacitko je vzdy ta mene dulezita volba, prave primarni.
 * U bezneho potvrzeni je primarni potvrzeni, u "zahodit zmeny?" je primarni zustat
 * (`primary: 'cancel'`) - potvrzeni je pak to sekundarni tlacitko vlevo.
 *
 * @param {{title: ?string, text: ?string, confirm: ?string, cancel: ?string, primary: ?('confirm'|'cancel'), onConfirm: function(): void, onCancel: ?function(): void}} options
 */
export const showConfirm = ({title = null, text = null, confirm = null, cancel = null, primary = 'confirm', onConfirm, onCancel = null}) => {
	const labels = defaults();
	const confirmButton = {label: confirm || labels.confirm, primary: primary === 'confirm', onClick: onConfirm};
	const cancelButton = {label: cancel || labels.cancel, primary: primary === 'cancel', onClick: onCancel};

	open({
		title,
		text,
		buttons: primary === 'cancel' ? [confirmButton, cancelButton] : [cancelButton, confirmButton],
		onDismiss: onCancel,
	});
};

/**
 * Informace s jedinym tlacitkem. Na rozdil od alert() nezastavi beh skriptu.
 *
 * @param {{title: ?string, text: ?string, close: ?string, onClose: ?function(): void}} options
 */
export const showAlert = ({title = null, text = null, close = null, onClose = null}) => {
	open({
		title,
		text,
		buttons: [{label: close || defaults().close, primary: true, onClick: onClose}],
		onDismiss: onClose,
	});
};

const confirmed = new WeakSet();

/**
 * Odkaz nebo tlacitko, ktere ma pred akci potvrzeni, se po "Potvrdit" klikne znovu.
 * Behem toho druheho kliknuti je element v `confirmed`, aby ho handlery pustily dal.
 *
 * @param {Element} el
 */
const replayClick = (el) => {
	confirmed.add(el);
	try {
		el.click();
	} finally {
		confirmed.delete(el);
	}
};

/**
 * @param {Element} el
 * @returns {boolean} element prave prochazi potvrzenym kliknutim
 */
export const isConfirmed = (el) => confirmed.has(el);

/**
 * Zepta se na potvrzeni a po nem element klikne znovu.
 *
 * @param {Element} el
 * @param {string} text
 */
export const confirmClick = (el, text) => {
	showConfirm({text, onConfirm: () => replayClick(el)});
};

/**
 * Odkazy s potvrzenim: `a.link-confirmation[data-confirm-text]` a datagridove
 * `[data-datagrid-confirm]`. Posloucha se ve fazi capture na dokumentu, takze klik
 * se zastavi drive, nez ho dostanou handlery primo na elementu (nette.ajax, datagrid) -
 * ajaxova i neajaxova akce se tak potvrzuji stejne.
 */
document.addEventListener('click', (e) => {
	const el = e.target.closest?.('a.link-confirmation, [data-datagrid-confirm]');
	if (!el || isConfirmed(el)) {
		return;
	}

	const text = el.matches('a.link-confirmation') ? el.dataset.confirmText : el.dataset.datagridConfirm;
	if (!text) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();
	confirmClick(el, text);
}, true);
