// Panel Úkoly v rychlé liště (assets/js/quickbar.js): úkol s href má název jako odkaz na detail,
// hotový ho označí jen zaškrtávátko; bez href zaškrtne úkol klik kamkoli na řádek.
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let setQuickbarSource;
let setDone;

function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel" data-quickbar-panel="todo" hidden>
			<div class="quickbar-panel-body" data-quickbar-list="todo" data-empty="Žádné úkoly."></div>
			<form class="quickbar-panel-form" data-quickbar-form="todo"><input type="text"></form>
		</section>
		<div class="quickbar" data-quickbar>
			<button type="button" data-quickbar-open="todo">Úkoly</button>
		</div>`;
}

const todos = [
	{ id: 1, text: 'Zavolat pacientovi', done: false, href: '/task?edit=1' },
	{ id: 2, text: 'Objednat materiál', done: false },
];

const row = (id) => document.querySelector(`[data-quickbar-todo="${id}"]`).closest('.quickbar-todo');

beforeAll(async () => {
	window.matchMedia = vi.fn((query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} }));
	Element.prototype.scrollIntoView = vi.fn();
	await import('../../assets/js/_jquery-global.js');
	({ setQuickbarSource } = await import('../../assets/js/quickbar.js'));

	document.body.innerHTML = quickbarHtml();
	document.dispatchEvent(new Event('DOMContentLoaded'));
	// Odkaz na detail jde v aplikaci ajaxem, jsdom nenavigovat
	document.addEventListener('click', (e) => {
		if (e.target.closest('a[href]')) {
			e.preventDefault();
		}
	});
});

beforeEach(async () => {
	setDone = vi.fn();
	setQuickbarSource({ todos: { list: () => todos.map((t) => ({ ...t })), setDone } });
	document.querySelector('[data-quickbar-open="todo"]').click();
	await new Promise((resolve) => setTimeout(resolve));
});

describe('úkoly', () => {
	it('název úkolu s detailem je odkaz a klik na něj úkol neukončí', () => {
		const link = row(1).querySelector('a[data-quickbar-todo-link]');
		expect(link.getAttribute('href')).toBe('/task?edit=1');

		link.querySelector('.quickbar-item-label').click();

		expect(setDone).not.toHaveBeenCalled();
		expect(row(1).querySelector('input').checked).toBe(false);
	});

	it('zaškrtávátko úkol ukončí', () => {
		row(1).querySelector('input').click();

		expect(setDone).toHaveBeenCalledWith(1, true);
	});

	it('úkol bez detailu zaškrtne klik na název', () => {
		expect(row(2).tagName).toBe('LABEL');
		row(2).querySelector('.quickbar-item-label').click();

		expect(setDone).toHaveBeenCalledWith(2, true);
	});
});
