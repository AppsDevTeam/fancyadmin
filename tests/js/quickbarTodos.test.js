// Panel Úkoly v rychlé liště (assets/js/quickbar.js): úkol s href má název jako odkaz na detail,
// hotový ho označí jen zaškrtávátko; bez href zaškrtne úkol klik kamkoli na řádek.
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let setQuickbarSource;
let setDone;
let listOptions;

function quickbarHtml() {
	return `
		<div class="quickbar-overlay" hidden></div>
		<section class="quickbar-panel" data-quickbar-panel="todo" hidden>
			<button type="button" class="btn btn-icon btn-sm btn-glass" data-show="Zobrazit hotové" data-hide="Skrýt hotové" data-quickbar-todo-done-toggle></button>
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
const doneTodo = { id: 3, text: 'Hotový úkol', done: true, href: '/task?edit=3' };

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
	listOptions = [];
	const list = (options) => {
		listOptions.push(options);
		return [...todos, ...(options?.withDone ? [doneTodo] : [])].map((t) => ({ ...t }));
	};
	setQuickbarSource({ todos: { list, setDone } });
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

	it('přepínač v hlavičce ukáže i hotové úkoly a volbu si pamatuje', async () => {
		const toggle = document.querySelector('[data-quickbar-todo-done-toggle]');
		expect(document.querySelector('[data-quickbar-todo="3"]')).toBeNull();

		toggle.click();
		await new Promise((resolve) => setTimeout(resolve));

		expect(listOptions.at(-1)).toEqual({ withDone: true });
		expect(document.querySelector('[data-quickbar-todo="3"]').checked).toBe(true);
		expect(toggle.getAttribute('aria-pressed')).toBe('true');
		expect(toggle.title).toBe('Skrýt hotové');
		expect(localStorage.getItem('fancyadmin-quickbar-todo-done')).toBe('1');

		toggle.click();
		await new Promise((resolve) => setTimeout(resolve));
		expect(document.querySelector('[data-quickbar-todo="3"]')).toBeNull();
	});
});
