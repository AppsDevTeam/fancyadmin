
import clamp from 'clamp-js';
import { onContentLoaded } from './naja';

// Klon přilepené hlavičky (datagridInfiniteScroll.js) je taky .table-responsive, stíny mu nastavuje tabulka
const TABLE_SELECTOR = '.table-responsive:not(.datagrid-sticky-thead__inner)';
const FIRST_COLUMN_SELECTOR = '.table-responsive tr > td:first-child > div:not(.item-detail-content)';

onContentLoaded(function ($el) {
	// Snippet bývá i řádek tabulky; nativní querySelectorAll najde prvky i podle předka mimo snippet (jQuery find ne)
	const find = (selector) => $el.get().flatMap((root) => [
		...(root.matches?.(selector) ? [root] : []),
		...root.querySelectorAll(selector),
	]);

	find(TABLE_SELECTOR).forEach(bindShadows);
	find('.table-responsive-wrapper').forEach(bindBottomScroll);

	// Nativní clamp jen nastaví styly, stačí jednou na nový obsah
	find(FIRST_COLUMN_SELECTOR).forEach((div) => clamp(div, { clamp: 2 }));
});

function bindShadows(responsive) {
	if (responsive.dataset.shadowBound) {
		return;
	}
	responsive.dataset.shadowBound = '1';

	const update = () => {
		const fromRight = responsive.scrollWidth - responsive.clientWidth - responsive.scrollLeft;
		const vars = {
			'--actionShadowWidthRight': fromRight > 1 ? '30px' : '0px',
			'--actionShadowWidthLeft': responsive.scrollLeft > 1 ? '30px' : '0px',
			// Šířka rozbaleného detailu řádku, který s tabulkou neroluje (_datagridDetail.scss)
			'--datagridViewportWidth': responsive.clientWidth + 'px',
		};
		const tables = [
			...responsive.querySelectorAll(':scope > table, :scope > .datagrid-tree'),
			...(responsive.parentElement?.querySelectorAll(':scope > .datagrid-sticky-thead table') ?? []),
		];
		tables.forEach((table) => Object.entries(vars).forEach(([name, value]) => table.style.setProperty(name, value)));
	};

	responsive.addEventListener('scroll', update, { passive: true });

	// Šířka tabulky se mění i bez resize okna (boční menu, panel, donačtené řádky)
	const observer = new ResizeObserver(update);
	observer.observe(responsive);
	Array.from(responsive.children).forEach((child) => observer.observe(child));
}

function bindBottomScroll(wrapper) {
	// Jen přímý potomek: první .table-responsive ve wrapperu je klon přilepené hlavičky (datagridInfiniteScroll.js)
	const responsive = wrapper.querySelector(':scope > .table-responsive');
	const bar = wrapper.querySelector(':scope > .table-bottom-scroll');
	if (!responsive || !bar || wrapper.dataset.bottomScrollBound) {
		return;
	}
	wrapper.dataset.bottomScrollBound = '1';

	const update = () => {
		const overflow = responsive.scrollWidth - responsive.clientWidth;
		bar.classList.toggle('d-none', overflow <= 1);
		if (overflow > 1) {
			bar.querySelector('.table-bottom-scroll-inner').style.width = bar.clientWidth + overflow + 'px';
			bar.scrollLeft = responsive.scrollLeft;
		}
	};

	// Šířka tabulky se mění i bez resize okna (boční menu, donačtené řádky, písmo)
	const observer = new ResizeObserver(update);
	observer.observe(responsive);
	Array.from(responsive.children).forEach((child) => observer.observe(child));

	responsive.addEventListener('scroll', () => {
		bar.scrollLeft = responsive.scrollLeft;
	}, { passive: true });

	bar.addEventListener('scroll', () => {
		responsive.scrollLeft = bar.scrollLeft;
	}, { passive: true });
}
