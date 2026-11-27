// Esc v poli formuláře jen vezme fokus, panel nebo modal zavře až další Esc (README, Fokus na první pole)
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let leaveFieldOnEscape;

function escape(target) {
	const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
	target.dispatchEvent(event);
	return event;
}

beforeAll(async () => {
	window.matchMedia = vi.fn((query) => ({ matches: true, media: query, addEventListener() {}, removeEventListener() {} }));
	await import('../../assets/js/_jquery-global.js');
	({ leaveFieldOnEscape } = await import('../../assets/js/formFocus.js'));
	await import('../../assets/js/lazyModal.js');
});

beforeEach(() => {
	document.body.innerHTML = '<div class="panel" tabindex="-1"><input name="a"><button type="button">OK</button></div>'
		+ '<div class="modal" tabindex="-1"><div class="modal-dialog"><input name="b"></div></div>';
});

describe('leaveFieldOnEscape', () => {
	it('Esc v poli dá fokus kontejneru', () => {
		const panel = document.querySelector('.panel');
		const input = panel.querySelector('input');
		input.focus();
		panel.addEventListener('keydown', (e) => leaveFieldOnEscape(e, panel), { once: true });

		const event = escape(input);

		expect(document.activeElement).toBe(panel);
		expect(event.defaultPrevented).toBe(true);
	});

	it('Esc na tlačítku ani na kontejneru nic nedělá', () => {
		const panel = document.querySelector('.panel');
		const handled = [];
		panel.addEventListener('keydown', (e) => handled.push(leaveFieldOnEscape(e, panel)));

		escape(panel.querySelector('button'));
		escape(panel);

		expect(handled).toEqual([false, false]);
	});

	it('Esc, který už zpracoval jiný prvek, nechá být', () => {
		const panel = document.querySelector('.panel');
		const input = panel.querySelector('input');
		input.focus();
		input.addEventListener('keydown', (e) => e.preventDefault());
		let handled = null;
		panel.addEventListener('keydown', (e) => { handled = leaveFieldOnEscape(e, panel); });

		escape(input);

		expect(handled).toBe(false);
		expect(document.activeElement).toBe(input);
	});
});

describe('Esc v modalu', () => {
	it('Esc v poli se k .modal nedostane (Bootstrap by modal zavřel), další Esc ano', () => {
		const modal = document.querySelector('.modal');
		const input = modal.querySelector('input');
		$(modal).trigger('show.bs.modal');
		const reachedModal = vi.fn();
		modal.addEventListener('keydown', reachedModal);

		input.focus();
		escape(input);
		expect(document.activeElement).toBe(modal);
		expect(reachedModal).not.toHaveBeenCalled();

		escape(modal);
		expect(reachedModal).toHaveBeenCalledTimes(1);

		$(modal).trigger('hidden.bs.modal');
	});
});
