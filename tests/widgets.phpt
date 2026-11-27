<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetAlert;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetBadge;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetCallout;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetChart;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetFigure;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetFunnelStep;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetPeriod;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetSplit;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetTableColumn;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetTile;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetTone;
use ADT\FancyAdmin\UI\Presenters\Dashboard;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Bridges\ApplicationLatte\UIExtension;
use Nette\DI\Compiler;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use Tester\Assert;

/**
 * Widgety (WidgetGridControl, docs/widgets.md): mrizka, poradi a sirka widgetu, widgety stat, tiles,
 * table, template a chart, line nacteni dat, vyber obdobi s prekreslenim jednoho widgetu, WidgetPeriod,
 * vic mrizek na strance i vnorena mrizka, tovarna v DI a trait presenteru.
 */

require __DIR__ . '/bootstrap.php';

final class DashboardTranslator implements Translator
{
	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		return '[' . $message . ']';
	}
}

final class DashboardLatteFactory implements LatteFactory
{
	public function create(?Nette\Application\UI\Control $control = null): Engine
	{
		$latte = new Engine();
		$latte->setTempDirectory(null);
		$latte->addExtension(new UIExtension($control));
		$latte->addExtension(new TranslatorExtension(new DashboardTranslator()));
		return $latte;
	}
}

final class DashboardTestPresenter extends Presenter
{
	use Dashboard;

	/** @var callable(WidgetGridControl): void */
	public $configure;

	public bool $ajax = false;

	public function isAjax(): bool
	{
		return $this->ajax;
	}

	protected function configureDashboard(WidgetGridControl $widgetGrid): void
	{
		($this->configure)($widgetGrid);
	}
}

function presenter(string $url = 'https://admin.test/admin/', bool $ajax = false): DashboardTestPresenter
{
	$presenter = new DashboardTestPresenter();
	$presenter->ajax = $ajax;
	$presenter->injectPrimary(new Request(new UrlScript($url)), new Response(), templateFactory: new TemplateFactory(new DashboardLatteFactory()));

	return $presenter;
}

function widgetGrid(?DashboardTestPresenter $presenter = null): WidgetGridControl
{
	$widgetGrid = new WidgetGridControl();
	($presenter ?? presenter())->addComponent($widgetGrid, 'dashboard');

	return $widgetGrid;
}

function renderWidgetGrid(WidgetGridControl $widgetGrid): string
{
	ob_start();
	try {
		$widgetGrid->render();
	} finally {
		$html = ob_get_clean();
	}

	return $html;
}

/** Obdobi, ktere widget preda do odkazu (null = parametr v adrese nebude). */
function linkPeriod(WidgetControl $control): ?string
{
	$params = [];
	$control->saveState($params);

	return $params['period'] ?? null;
}

/** @return list<string> jmena widgetu v poradi vykresleni */
function widgetNames(string $html): array
{
	preg_match_all('~data-widget="([^"]+)"~', $html, $match);

	return $match[1];
}

test('mrizka ma pocet sloupcu, widgety jdou podle pozice a sirka se orizne na mrizku', function () {
	$widgetGrid = widgetGrid()->setColumns(2);
	$widgetGrid->addStat('second', 'Druhy', fn() => 2)->setPosition(10);
	$widgetGrid->addStat('first', 'Prvni', fn() => 1)->setPosition(-1);
	$widgetGrid->addStat('third', 'Treti', fn() => 3)->setPosition(10)->setSpan(5);

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<div class="fa-widgets fa-widgets-cols-2">', $html);
	Assert::same(['first', 'second', 'third'], widgetNames($html));
	Assert::contains('class="fa-widget fa-widget-stat fa-widgets-span-2" data-widget="third"', $html);
	Assert::contains('class="fa-widget fa-widget-stat fa-widgets-span-1" data-widget="first"', $html);
});

test('stat vypise formatovane cislo, poznamku, ikonu, ton a odkaz', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addStat('pending', 'Čeká <b>na</b> odeslání', fn() => 12345)
		->setNote(fn() => 'objednávek')
		->setIcon('truck')
		->setTone(WidgetTone::Warning)
		->setLink('/admin/order');
	$widgetGrid->addStat('rate', 'Podíl', fn() => 12.5)->setIcon('fa-regular fa-percent');

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<div class="fa-widgets-stat fa-widgets-tone-warning">', $html);
	Assert::contains('<div class="fa-widgets-stat-value">12' . "\u{a0}" . '345</div>', $html);
	Assert::contains('<div class="fa-widgets-stat-note">objednávek</div>', $html);
	Assert::contains('<i class="fa-solid fa-truck" aria-hidden="true"></i>', $html);
	Assert::contains('<i class="fa-regular fa-percent" aria-hidden="true"></i>', $html);
	Assert::contains('<div class="fa-widgets-stat-value">12,50</div>', $html);
	Assert::contains('Čeká &lt;b&gt;na&lt;/b&gt; odeslání', $html);
	Assert::contains('<a href="/admin/order" class="stretched-link"', $html);
	Assert::notContains('fa-widget-header', $html);
});

test('dlazdice se vykresli bez karty widgetu, s titulkem jen kdyz ho widget ma', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addTiles('kpi', fn() => [
		new WidgetTile('Katalog', 80955, '15 105 v prodeji', 'box'),
		new WidgetTile('Málo skladem', 3, null, 'triangle-exclamation', '/admin/warehouse', WidgetTone::Danger),
	])->setSpan(3);

	$html = renderWidgetGrid($widgetGrid);

	Assert::same(2, substr_count($html, 'class="card fa-widgets-tile '));
	Assert::contains('<div class="card fa-widgets-tile fa-widgets-tone-danger">', $html);
	Assert::contains('<div class="fa-widgets-tile-value">80' . "\u{a0}" . '955</div>', $html);
	Assert::contains('15 105 v prodeji', $html);
	Assert::notContains('fa-widgets-card', $html);
	Assert::notContains('fa-widget-header', $html);

	$widgetGrid = widgetGrid();
	$widgetGrid->addTiles('kpi', fn() => ['neni dlazdice']);
	Assert::exception(fn() => renderWidgetGrid($widgetGrid), UnexpectedValueException::class, "%a%'kpi'%a%");
});

test('tabulka formatuje ciselne sloupce, Html nechava a prazdna hlasi prazdna data', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addTable('sales', 'Prodeje', [
		'period' => 'Období',
		'orders' => new WidgetTableColumn('Objednávky', numeric: true),
		'sales' => new WidgetTableColumn('Tržba', numeric: true),
	], fn() => [
		['period' => '<i>Dnes</i>', 'orders' => 1200, 'sales' => Html::el('strong')->setText('5 000 Kč')],
	])->setDescription('Bez <b>stornovaných</b>');
	$widgetGrid->addTable('empty', 'Prázdná', ['a' => 'A'], fn() => []);
	$widgetGrid->addTable('wrapped', 'Zalomená', ['a' => new WidgetTableColumn('A', wrap: true), 'b' => 'B'], fn() => [['a' => 'dlouhý text', 'b' => 'x']]);
	$widgetGrid->addKpi('kpi', 'Obrat', fn() => 1)->setDescription('Realizované provize');

	$html = renderWidgetGrid($widgetGrid);

	// tabulka má popis na otazníku (výchozí vzhled), tooltip na nadpisu mají jen karty kpi, figures a alerts
	Assert::contains('<h2 class="fa-widget-title">Prodeje</h2>', $html);
	Assert::contains('data-bs-toggle="tooltip" data-bs-title="Bez &amp;lt;b&amp;gt;stornovaných&amp;lt;/b&amp;gt;" aria-label="Bez &lt;b&gt;stornovaných&lt;/b&gt;"', $html);
	Assert::notContains('fa-widget-description', $html);
	Assert::contains('<th class="text-end" scope="col">Objednávky</th>', $html);
	Assert::contains('<td>&lt;i&gt;Dnes&lt;/i&gt;</td>', $html);
	Assert::contains('<td class="text-end text-nowrap">1' . "\u{a0}" . '200</td>', $html);
	Assert::contains('<td class="text-end text-nowrap"><strong>5 000 Kč</strong></td>', $html);
	Assert::contains('<p class="fa-widgets-empty">[fcadmin.widgets.empty]</p>', $html);
	Assert::contains('<td class="fa-widgets-wrap">dlouhý text</td><td>x</td>', preg_replace('~>\s+<~', '><', $html));
	Assert::contains('<h2 class="fa-widget-title" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Realizované provize">Obrat</h2>', $html);
	Assert::same(1, substr_count($html, 'fa-widget-hint'));
});

test('sablona widgetu dostane parametry, presenter, dashboard i widget', function () {
	$file = tempnam(sys_get_temp_dir(), 'dashboard') . '.latte';
	file_put_contents($file, '<p class="custom">{$label} {$widget->getName()} {$widgetGrid->getColumns()} {$presenter::class}</p>');

	try {
		$widgetGrid = widgetGrid();
		$widgetGrid->addTemplate('export', 'Export', $file, fn() => ['label' => 'Stáhnout']);

		$html = renderWidgetGrid($widgetGrid);
	} finally {
		unlink($file);
	}

	Assert::contains('<h2 class="fa-widget-title">Export</h2>', $html);
	Assert::contains('<p class="custom">Stáhnout export 3 DashboardTestPresenter</p>', $html);
});

test('data widgetu se nactou az pri vykresleni a jen jednou', function () {
	$calls = 0;
	$widgetGrid = widgetGrid();
	$widgetGrid->addStat('lazy', 'Líný', function () use (&$calls) {
		$calls++;
		return 1;
	});
	$widgetGrid->addTable('lazyTable', 'Líná', ['a' => 'A'], function () use (&$calls) {
		$calls++;
		return [['a' => 1]];
	});

	Assert::same(0, $calls);
	renderWidgetGrid($widgetGrid);
	Assert::same(2, $calls);
});

test('neplatne nastaveni skonci vyjimkou', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addStat('same', 'A', fn() => 1);

	Assert::exception(fn() => $widgetGrid->addStat('same', 'B', fn() => 2), InvalidArgumentException::class, "%a%'same'%a%");
	Assert::exception(fn() => $widgetGrid->addStat('1bad name', 'B', fn() => 2), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->addStat('with-dash', 'B', fn() => 2), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->getWidget('same')->setPeriods(WidgetPeriod::TODAY, [WidgetPeriod::THIS_MONTH]), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->setColumns(0), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->setColumns(13), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->getWidget('same')->setSpan(0), InvalidArgumentException::class);
	Assert::exception(fn() => $widgetGrid->getWidget('missing'), InvalidArgumentException::class);
});

test('trait presenteru vytvori komponentu dashboard z tovarny a necha ji projektu nastavit', function () {
	$presenter = new DashboardTestPresenter();
	$presenter->configure = fn(WidgetGridControl $widgetGrid) => $widgetGrid->setColumns(4)->addStat('a', 'A', fn() => 1);
	$factory = new class implements WidgetGridControlFactory {
		public function create(): WidgetGridControl
		{
			return new WidgetGridControl();
		}
	};

	$widgetGrid = $presenter->createComponentDashboard($factory);

	Assert::same(4, $widgetGrid->getColumns());
	Assert::same(['a'], array_map(fn($widget) => $widget->getName(), $widgetGrid->getWidgets()));
});

test('rozsireni registruje tovarnu dashboardu', function () {
	$builder = new ContainerBuilder();
	$builder->parameters['appDir'] = sys_get_temp_dir();
	$extension = new FancyAdminExtension();
	$compiler = new Compiler($builder);
	$extension->setCompiler($compiler, 'fancyadmin');
	$extension->setConfig(['locksDir' => '/var/lock', 'colors' => array_fill_keys([
		'backgroundColor', 'dashboardAccentColor', 'primaryColor', 'primaryColorDark', 'primaryColorDark20',
		'secondaryColor', 'secondaryColorDark', 'secondaryColorDarker', 'ternaryColor', 'ternaryTextColor',
		'loginBackground', 'loginInputTextColor', 'loginBackgroundInput', 'loginBackgroundInputFocus',
		'inputBorder', 'inputFocusBorder', 'inputFocusBackground',
	], '#000000')]);
	$extension->loadConfiguration();

	$definition = $builder->getDefinition('fancyadmin.widgetGridControlFactory');
	Assert::type(FactoryDefinition::class, $definition);
	Assert::same(WidgetGridControlFactory::class, $definition->getImplement());
	Assert::same(WidgetGridControl::class, $definition->getResultDefinition()->getFactory()->getEntity());
});

test('vyber obdobi: data dostanou zvolene obdobi, neplatne a nenabizene je vychozi, vychozi nejde do odkazu', function () {
	$received = [];
	$widgetGrid = widgetGrid();
	$widgetGrid->addTable('sales', 'Prodeje', ['a' => 'A'], function (?WidgetPeriod $period) use (&$received) {
		$received[] = $period;
		return [];
	})->setPeriods(WidgetPeriod::TODAY, [WidgetPeriod::TODAY, WidgetPeriod::THIS_MONTH]);
	$control = $widgetGrid['sales'];

	$control->loadState(['period' => 'thisMonth']);
	Assert::same(WidgetPeriod::THIS_MONTH, $widgetGrid->getWidget('sales')->getPeriod());
	Assert::same('thisMonth', linkPeriod($control));

	foreach (['nesmysl', 'thisYear', 'today'] as $value) {
		$control->loadState(['period' => $value]);
		Assert::same(WidgetPeriod::TODAY, $widgetGrid->getWidget('sales')->getPeriod(), $value);
		Assert::null(linkPeriod($control), $value);
	}

	$control->loadState(['period' => 'thisMonth']);
	renderWidgetGrid($widgetGrid);
	Assert::same([WidgetPeriod::THIS_MONTH], $received);
});

test('widget bez obdobi nema vyber a jeho data dostanou null', function () {
	$received = 'nevolano';
	$widgetGrid = widgetGrid();
	$widgetGrid->addStat('pending', 'Čeká', function ($period) use (&$received) {
		$received = $period;
		return 1;
	});
	$widgetGrid['pending']->loadState(['period' => 'today']);

	$html = renderWidgetGrid($widgetGrid);

	Assert::null($received);
	Assert::null(linkPeriod($widgetGrid['pending']));
	Assert::notContains('fa-widgets-period', $html);
});

test('formular obdobi: select s volbami, skryta pole ostatnich parametru stranky a noscript odeslani', function () {
	$widgetGrid = widgetGrid(presenter('https://admin.test/admin/?dashboard-orders-period=lastMonth&dashboard-sales-period=today&dashboard-sales-redraw=1&filter%5Bstate%5D=2'));
	$widgetGrid->addTable('sales', 'Prodeje', ['a' => 'A'], fn() => [])->setPeriods(WidgetPeriod::THIS_MONTH, [WidgetPeriod::TODAY, WidgetPeriod::THIS_MONTH]);
	$widgetGrid['sales']->loadState(['period' => 'today']);

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<form class="fa-widgets-period" method="get" action="/admin/">', $html);
	Assert::contains('<input type="hidden" name="dashboard-orders-period" value="lastMonth">', $html);
	Assert::contains('<input type="hidden" name="filter[state]" value="2">', $html);
	Assert::notContains('type="hidden" name="dashboard-sales-period"', $html);
	Assert::notContains('redraw" value', $html);
	Assert::match('%A%<select name="dashboard-sales-period" class="form-select form-select-sm" aria-label="[fcadmin.widgets.period.label]"%A%data-widget-period data-widget-redraw="dashboard-sales-redraw">%A%', $html);
	Assert::contains('<option value="today" selected>[fcadmin.widgets.period.today]</option>', $html);
	Assert::contains('<option value="thisMonth">[fcadmin.widgets.period.thisMonth]</option>', $html);
	Assert::notContains('thisYear', $html);
	Assert::contains('<noscript><button type="submit" class="btn btn-sm btn-outline-secondary">[fcadmin.widgets.period.submit]</button></noscript>', $html);
});

test('ajax s redraw prekresli jen snippet toho widgetu a data ostatnich se nenactou', function () {
	$calls = [];
	$widgetGrid = widgetGrid(presenter(ajax: true));
	foreach (['sales', 'orders'] as $name) {
		$widgetGrid->addTable($name, $name, ['a' => 'A'], function (?WidgetPeriod $period) use (&$calls, $name) {
			$calls[] = $name;
			return [['a' => $period->value]];
		})->setPeriods(WidgetPeriod::TODAY);
	}
	$widgetGrid->addStat('pending', 'Čeká', function () use (&$calls) {
		$calls[] = 'pending';
		return 1;
	});

	$widgetGrid['orders']->loadState(['period' => 'yesterday']);
	$widgetGrid['sales']->loadState(['period' => 'lastMonth', 'redraw' => '1']);
	Assert::true($widgetGrid['sales']->isControlInvalid('body'));
	Assert::false($widgetGrid['orders']->isControlInvalid());

	$widgetGrid->snippetMode = true;
	$output = renderWidgetGrid($widgetGrid);

	$snippets = (array) $widgetGrid->getPresenter()->getPayload()->snippets;
	Assert::same(['snippet-dashboard-sales-body'], array_keys($snippets));
	Assert::contains('lastMonth', $snippets['snippet-dashboard-sales-body']);
	Assert::same(['sales'], $calls);
	Assert::same('', trim($output));
});

test('redraw bez ajaxu nic neprekresluje (cela stranka)', function () {
	$widgetGrid = widgetGrid(presenter(ajax: false));
	$widgetGrid->addTable('sales', 'Prodeje', ['a' => 'A'], fn() => [])->setPeriods(WidgetPeriod::TODAY);
	$widgetGrid['sales']->loadState(['redraw' => '1']);

	Assert::false($widgetGrid['sales']->isControlInvalid());
});

/** Presenter s parametry adresy rozdelenymi na komponenty, jako po run() - stav komponent se nacte pri pripojeni. */
function pagePresenter(string $query, bool $ajax = false): DashboardTestPresenter
{
	$presenter = presenter('https://admin.test/admin/?' . $query, $ajax);
	parse_str($query, $params);
	Closure::bind(function () use ($params) {
		$this->request = new Nette\Application\Request('Test', 'GET', $params);
		$this->initGlobalParameters();
	}, $presenter, Presenter::class)();

	return $presenter;
}

/**
 * Mrizka nastavena pred pripojenim, jako z createComponent*() - widgety dostanou stav az s obdobim.
 *
 * @param list<string> $calls
 */
function salesGrid(array &$calls, string $prefix): WidgetGridControl
{
	$widgetGrid = new WidgetGridControl();
	$widgetGrid->addTable('sales', 'Prodeje', ['a' => 'A'], function (?WidgetPeriod $period) use (&$calls, $prefix) {
		$calls[] = $prefix . ':' . $period->value;
		return [['a' => $period->value]];
	})->setPeriods(WidgetPeriod::TODAY);

	return $widgetGrid;
}

test('dve mrizky na jedne strance: stejne jmeno widgetu, vlastni obdobi, select, formular a redraw', function () {
	$calls = [];
	$presenter = pagePresenter('orders-sales-period=lastMonth&customers-sales-period=yesterday');
	$presenter->addComponent($orders = salesGrid($calls, 'orders'), 'orders');
	$presenter->addComponent($customers = salesGrid($calls, 'customers'), 'customers');

	Assert::same(WidgetPeriod::LAST_MONTH, $orders->getWidget('sales')->getPeriod());
	Assert::same(WidgetPeriod::YESTERDAY, $customers->getWidget('sales')->getPeriod());

	$html = renderWidgetGrid($orders) . renderWidgetGrid($customers);

	Assert::same(['orders:lastMonth', 'customers:yesterday'], $calls);
	Assert::contains('id="snippet-orders-sales-body"', $html);
	Assert::contains('id="snippet-customers-sales-body"', $html);
	Assert::contains('<select name="orders-sales-period"', $html);
	Assert::contains('data-widget-redraw="orders-sales-redraw"', $html);
	Assert::contains('<select name="customers-sales-period"', $html);
	Assert::contains('data-widget-redraw="customers-sales-redraw"', $html);
	// Formular jedne mrizky nese obdobi druhe, bez JS by se jinak ztratilo
	Assert::contains('<input type="hidden" name="customers-sales-period" value="yesterday">', $html);
	Assert::contains('<input type="hidden" name="orders-sales-period" value="lastMonth">', $html);
});

test('dve mrizky: redraw jedne prekresli jen jeji widget, data druhe se nenactou', function () {
	$calls = [];
	$presenter = pagePresenter('orders-sales-period=lastMonth&customers-sales-period=yesterday&customers-sales-redraw=1', ajax: true);
	$presenter->addComponent($orders = salesGrid($calls, 'orders'), 'orders');
	$presenter->addComponent($customers = salesGrid($calls, 'customers'), 'customers');

	Assert::false($orders['sales']->isControlInvalid());
	Assert::true($customers['sales']->isControlInvalid('body'));

	$orders->snippetMode = $customers->snippetMode = true;
	renderWidgetGrid($orders);
	renderWidgetGrid($customers);

	$snippets = (array) $presenter->getPayload()->snippets;
	Assert::same(['snippet-customers-sales-body'], array_keys($snippets));
	Assert::same(['customers:yesterday'], $calls);
});

test('widget pridany az do pripojene mrizky drzi obdobi z URL v datech i v odkazu', function () {
	$received = [];
	$presenter = pagePresenter('stats-sales-period=lastMonth');
	$presenter->addComponent($widgetGrid = new WidgetGridControl(), 'stats');
	$widgetGrid->addTable('sales', 'Prodeje', ['a' => 'A'], function (?WidgetPeriod $period) use (&$received) {
		$received[] = $period;
		return [];
	})->setPeriods(WidgetPeriod::TODAY);

	renderWidgetGrid($widgetGrid);

	Assert::same([WidgetPeriod::LAST_MONTH], $received);
	Assert::same('lastMonth', linkPeriod($widgetGrid['sales']));
});

test('mrizka vnorena v jine komponente ma parametry pod celou cestou', function () {
	$calls = [];
	$panel = new class extends Nette\Application\UI\Control {
	};
	$panel->addComponent($widgetGrid = salesGrid($calls, 'stats'), 'stats');
	pagePresenter('panel-stats-sales-period=thisWeek')->addComponent($panel, 'panel');

	Assert::same(WidgetPeriod::THIS_WEEK, $widgetGrid->getWidget('sales')->getPeriod());

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('id="snippet-panel-stats-sales-body"', $html);
	Assert::contains('<select name="panel-stats-sales-period"', $html);
	Assert::contains('data-widget-redraw="panel-stats-sales-redraw"', $html);
});

/** @return array{string, string} */
function periodRange(WidgetPeriod $period, string $now, bool $previous = false): array
{
	$range = $previous ? $period->getPreviousRange(new DateTimeImmutable($now)) : $period->getRange(new DateTimeImmutable($now));

	return [$range[0]->format('Y-m-d H:i'), $range[1]->format('Y-m-d H:i')];
}

test('WidgetPeriod: rozsah vsech obdobi, konec vylucny, tyden od pondeli', function () {
	$now = '2026-11-18 14:35'; // streda
	Assert::same(['2026-11-18 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::TODAY, $now));
	Assert::same(['2026-11-17 00:00', '2026-11-18 00:00'], periodRange(WidgetPeriod::YESTERDAY, $now));
	Assert::same(['2026-11-16 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::THIS_WEEK, $now));
	Assert::same(['2026-11-12 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::LAST_7_DAYS, $now));
	Assert::same(['2026-11-01 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::THIS_MONTH, $now));
	Assert::same(['2026-10-01 00:00', '2026-11-01 00:00'], periodRange(WidgetPeriod::LAST_MONTH, $now));
	Assert::same(['2026-10-01 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::THIS_QUARTER, $now));
	Assert::same(['2026-01-01 00:00', '2026-11-19 00:00'], periodRange(WidgetPeriod::THIS_YEAR, $now));

	Assert::same(['2026-11-16 00:00', '2026-11-17 00:00'], periodRange(WidgetPeriod::THIS_WEEK, '2026-11-16 08:00'), 'v pondeli jen dnesek');
	Assert::same(['2026-12-01 00:00', '2027-01-01 00:00'], periodRange(WidgetPeriod::LAST_MONTH, '2027-01-05'), 'minuly mesic pres rok');
	Assert::same(['2027-01-01 00:00', '2027-01-06 00:00'], periodRange(WidgetPeriod::THIS_QUARTER, '2027-01-05'));
	Assert::same(['2026-07-01 00:00', '2026-09-30 00:00'], periodRange(WidgetPeriod::THIS_QUARTER, '2026-09-29 23:59'));
});

test('WidgetPeriod: predchozi obdobi je stejne dlouhe a nepresahuje do zvoleneho', function () {
	$now = '2026-11-18 14:35';
	Assert::same(['2026-11-17 00:00', '2026-11-18 00:00'], periodRange(WidgetPeriod::TODAY, $now, true));
	Assert::same(['2026-11-16 00:00', '2026-11-17 00:00'], periodRange(WidgetPeriod::YESTERDAY, $now, true));
	Assert::same(['2026-11-09 00:00', '2026-11-12 00:00'], periodRange(WidgetPeriod::THIS_WEEK, $now, true));
	Assert::same(['2026-11-05 00:00', '2026-11-12 00:00'], periodRange(WidgetPeriod::LAST_7_DAYS, $now, true));
	Assert::same(['2026-10-01 00:00', '2026-10-19 00:00'], periodRange(WidgetPeriod::THIS_MONTH, $now, true));
	Assert::same(['2026-09-01 00:00', '2026-10-01 00:00'], periodRange(WidgetPeriod::LAST_MONTH, $now, true));
	Assert::same(['2026-07-01 00:00', '2026-08-19 00:00'], periodRange(WidgetPeriod::THIS_QUARTER, $now, true));
	Assert::same(['2025-01-01 00:00', '2025-11-19 00:00'], periodRange(WidgetPeriod::THIS_YEAR, $now, true));

	Assert::same(['2026-02-01 00:00', '2026-03-01 00:00'], periodRange(WidgetPeriod::THIS_MONTH, '2026-03-31', true), 'delsi nez unor se zkrati na jeho konec');
	Assert::same(['2026-01-01 00:00', '2026-02-01 00:00'], periodRange(WidgetPeriod::LAST_MONTH, '2026-03-10', true), 'minuly mesic (unor) proti celemu lednu');
	Assert::same(['2026-12-01 00:00', '2026-12-06 00:00'], periodRange(WidgetPeriod::THIS_MONTH, '2027-01-05', true), 'pres prelom roku');
	Assert::same(['2027-01-01 00:00', '2028-01-01 00:00'], periodRange(WidgetPeriod::THIS_YEAR, '2028-12-31', true), 'prestupny rok proti celemu predchozimu');
	Assert::same(['2026-12-31 00:00', '2027-01-01 00:00'], periodRange(WidgetPeriod::TODAY, '2027-01-01 00:00', true));
});

test('WidgetPeriod: hodnota z URL a klic prekladu', function () {
	Assert::same(WidgetPeriod::LAST_MONTH, WidgetPeriod::fromUrl('lastMonth', WidgetPeriod::TODAY));
	Assert::same(WidgetPeriod::TODAY, WidgetPeriod::fromUrl('nesmysl', WidgetPeriod::TODAY));
	Assert::same(WidgetPeriod::TODAY, WidgetPeriod::fromUrl(null, WidgetPeriod::TODAY));
	Assert::same('fcadmin.widgets.period.last7Days', WidgetPeriod::LAST_7_DAYS->getTranslationKey());
});

/** @return array<string, mixed> konfigurace z data-adt-chart */
function chartConfig(string $html): array
{
	// uvozovky atributu volí Latte podle verze, obsah je vždy escapovaný
	Assert::same(1, preg_match("~<canvas data-adt-chart=(['\"])([^'\"]+)\\1 role=\"img\" aria-label=\"([^\"]*)\"></canvas>~", $html, $match));

	return json_decode(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
}

test('graf: canvas s konfiguraci pro builtin Chart, plugin tematu, format a zvolene obdobi', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addChart('revenue', 'Tržba "po dnech"', fn(?WidgetPeriod $period) => new WidgetChart(
		'line',
		['1. 11.', '2. 11.'],
		[['label' => $period->value, 'data' => [1200.5, 0]], ['label' => "Předchozí 'rok'", 'data' => [800, 100], 'borderDash' => [4, 4]]],
		format: ['style' => 'currency', 'currency' => 'CZK', 'maximumFractionDigits' => 0],
		options: ['interaction' => ['intersect' => true], 'plugins' => ['legend' => ['position' => 'bottom']]],
	))->setPeriods(WidgetPeriod::THIS_MONTH);
	$widgetGrid['revenue']->loadState(['period' => 'lastMonth']);

	$html = renderWidgetGrid($widgetGrid);
	$config = chartConfig($html);

	Assert::contains('<div class="fa-widgets-chart">', $html);
	Assert::contains('aria-label="Tržba &quot;po dnech&quot;"', $html);
	Assert::same('line', $config['type']);
	Assert::same(['1. 11.', '2. 11.'], $config['data']['labels']);
	Assert::same('lastMonth', $config['data']['datasets'][0]['label']);
	Assert::same([1200.5, 0], $config['data']['datasets'][0]['data']);
	Assert::same("Předchozí 'rok'", $config['data']['datasets'][1]['label']);
	Assert::same([4, 4], $config['data']['datasets'][1]['borderDash']);
	Assert::same(['fancyadminTheme'], $config['plugins']);
	Assert::same(['style' => 'currency', 'currency' => 'CZK', 'maximumFractionDigits' => 0], $config['format']);
	Assert::false($config['options']['maintainAspectRatio']);
	Assert::same(['mode' => 'index', 'intersect' => true], $config['options']['interaction']);
	Assert::same(['legend' => ['position' => 'bottom']], $config['options']['plugins']);
	Assert::false(isset($config['options']['indexAxis']));
});

test('graf: vodorovne sloupce jsou vyssi, prazdna data hlasi prazdne obdobi, spatny navrat a typ vyjimkou', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addChart('top', 'Top 10', fn() => new WidgetChart('bar', ['A'], [['label' => 'Kusy', 'data' => [5]]], indexAxis: 'y'));
	$widgetGrid->addChart('empty', 'Prázdný', fn() => new WidgetChart('line', [], []));

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<div class="fa-widgets-chart fa-widgets-chart-tall">', $html);
	Assert::same('y', chartConfig($html)['options']['indexAxis']);
	Assert::contains('<p class="fa-widgets-empty">[fcadmin.widgets.empty]</p>', $html);

	$widgetGrid = widgetGrid();
	$widgetGrid->addChart('wrong', 'Špatně', fn() => ['type' => 'line']);
	Assert::exception(fn() => renderWidgetGrid($widgetGrid), UnexpectedValueException::class, "%a%'wrong'%a%");

	Assert::exception(fn() => new WidgetChart('scatter3d', [], []), InvalidArgumentException::class);
	Assert::exception(fn() => new WidgetChart('bar', ['A'], [['label' => 'bez dat']]), InvalidArgumentException::class);
	Assert::exception(fn() => new WidgetChart('bar', [], [], indexAxis: 'z'), InvalidArgumentException::class);
});

test('kpi: hodnota s jednotkou, krivka vyvoje, stitky pod hodnotou, popisek a proklik cele karty', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addKpi('turnover', 'Obrat', fn() => '42,8')
		->setUnit('mil. Kč')
		->setTone(WidgetTone::Success)
		->setSparkline(fn() => [1, null, 3, 2])
		->setBadges(fn() => [new WidgetBadge('↑ 8,4 %', WidgetTone::Success, 'meziročně')])
		->setNote('123 % plánu')
		->setLink('/admin/sales');
	$widgetGrid->addKpi('flat', 'Bez křivky', fn() => null)->setSparkline(fn() => [5])->setUnit(fn(?WidgetPeriod $period) => 'ks ' . ($period?->value ?? 'bez období'));

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<h2 class="fa-widget-title">Obrat</h2>', $html);
	Assert::match('%A%<div class="fa-widgets-kpi-value fa-widgets-value-tone-success">%A%42,8<span class="fa-widgets-unit">mil. Kč</span>%A%', $html);
	// null mezi hodnotami vypadne, tři body na šířku 66 px, poslední nahoře
	Assert::contains('<polyline points="3,25 36,3 69,14"', $html);
	Assert::contains('<circle cx="69" cy="14" r="2.6"', $html);
	Assert::contains('<span class="fa-widgets-badge fa-widgets-tone-success" title="meziročně">↑ 8,4 %</span>', $html);
	Assert::contains('<span class="fa-widgets-kpi-note">123 % plánu</span>', $html);
	Assert::contains('<a href="/admin/sales" class="stretched-link fa-widgets-link" aria-label="Obrat"></a>', $html);
	Assert::contains('fa-widgets-link-arrow', $html);
	// štítky KPI nejsou v hlavičce, jen pod hodnotou
	Assert::same(1, substr_count($html, '↑ 8,4 %'));
	// prázdná hodnota je pomlčka, jedna hodnota křivku nedělá
	Assert::match('%A%<div class="fa-widgets-kpi-value fa-widgets-value-tone-neutral">%A%–%A%', $html);
	Assert::same(1, substr_count($html, '<svg'));
	Assert::contains('<span class="fa-widgets-unit">ks bez období</span>', $html);
});

test('kpi s castmi: cast ma popisek, hodnotu, jednotku, stitky a poznamku; hodnota nebo casti, ne oboje', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addKpiParts('network', 'Pobočky a makléři', fn() => [
		new WidgetFigure('Pobočky', 27, note: 'celkem v síti'),
		new WidgetFigure('Makléři', 286, badges: [new WidgetBadge('+34 noví', WidgetTone::Success), new WidgetBadge('−14 ukončeno', WidgetTone::Danger)]),
	]);

	$html = renderWidgetGrid($widgetGrid);

	Assert::same(2, substr_count($html, 'class="fa-widgets-kpi-part"'));
	Assert::contains('<div class="fa-widgets-kpi-part-label">Pobočky</div>', $html);
	Assert::contains('<span class="fa-widgets-kpi-note">celkem v síti</span>', $html);
	Assert::contains('<span class="fa-widgets-badge fa-widgets-tone-danger">−14 ukončeno</span>', $html);

	Assert::exception(fn() => new ADT\FancyAdmin\UI\Components\Controls\Widgets\KpiWidget('x', 'X'), InvalidArgumentException::class);
	$widgetGrid = widgetGrid();
	$widgetGrid->addKpiParts('bad', 'Špatně', fn() => ['neni cast']);
	Assert::exception(fn() => renderWidgetGrid($widgetGrid), UnexpectedValueException::class, "%a%'bad'%a%");
});

test('rada udaju: hodnota v barve tonu, jednotka, doplnek, bublina popisku a stitky v hlavicce s podtitulem', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addFigures('recruitment', 'Nábor nemovitostí', fn() => [
		new WidgetFigure('Nové nábory', 124),
		new WidgetFigure('Exkluzivita', '58 %', note: 'z aktivních', tone: WidgetTone::Success, title: 'Podíl exkluzivních'),
		new WidgetFigure('Ztracená provize', '3,35', unit: 'mil. Kč', tone: WidgetTone::Danger),
	])->setSubtitle(fn() => 'říjen 2026')
		->setBadges([new WidgetBadge('3 pobočky po splatnosti', WidgetTone::Danger)]);
	$widgetGrid->addFigures('empty', 'Prázdná', fn() => []);

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<span class="fa-widget-subtitle">říjen 2026</span>', $html);
	Assert::contains('<span class="fa-widgets-badge fa-widgets-tone-danger">3 pobočky po splatnosti</span>', $html);
	Assert::contains('<div class="fa-widgets-figure-label" title="Podíl exkluzivních">Exkluzivita</div>', $html);
	Assert::match('%A%fa-widgets-value-tone-success">%A%58 %%%A%<span class="fa-widgets-figure-note">z aktivních</span>%A%', $html);
	Assert::match('%A%fa-widgets-value-tone-danger">%A%3,35<span class="fa-widgets-unit">mil. Kč</span>%A%', $html);
	Assert::match('%A%fa-widgets-value-tone-neutral">%A%124%A%', $html);
	Assert::contains('[fcadmin.widgets.empty]', $html);
});

test('volitelne: barevna linka KPI karty (setAccent) a rada udaju jako samostatne dlazdice (setCards); bez voleb beze zmeny', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addKpi('realized', 'Tržba', fn() => 976)->setTone(WidgetTone::Success)->setAccent();
	$widgetGrid->addKpi('plainKpi', 'Bez linky', fn() => 1)->setTone(WidgetTone::Success);
	$widgetGrid->addFigures('cards', 'Realizace', fn() => [new WidgetFigure('Počet realizací', 10, unit: 'ks', note: 'uzavřené smlouvy')])->setCards();
	$widgetGrid->addFigures('plainFigures', 'V kartě', fn() => [new WidgetFigure('Nové nábory', 124)]);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));
	$part = fn(string $name) => substr($html, strpos($html, 'data-widget="' . $name . '"'), 900);

	Assert::contains('<div class="card fa-widgets-card fa-widgets-accent fa-widgets-tone-success">', $part('realized'));
	Assert::notContains('fa-widgets-accent', $part('plainKpi'));
	// dlaždice: každý údaj je karta, widget sám kartu ani nadpis nemá
	Assert::contains('<div class="fa-widgets-figures fa-widgets-figures-cards"><div class="fa-widgets-figure card fa-widgets-card">', $part('cards'));
	Assert::notContains('Realizace', $part('cards'));
	Assert::contains('<div class="card fa-widgets-card"><div class="fa-widget-header">', $part('plainFigures'));
	Assert::contains('<div class="fa-widgets-figures"><div class="fa-widgets-figure">', $part('plainFigures'));
});

test('upozorneni: zavaznost, poznamka, hodnota, proklik s vychozim popiskem a odkaz v paticce; prazdny seznam', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addAlerts('alerts', 'Upozornění k řešení', fn() => [
		new WidgetAlert('Plzeň: realizované provize −28 %', WidgetTone::Danger, link: '/admin/branch/1', linkLabel: 'Řešit'),
		new WidgetAlert('12 makléřů bez náboru', note: '30+ dní', value: 12, link: '/admin/user'),
	])->setLink('/admin/alerts', 'Všechna upozornění');
	$widgetGrid->addAlerts('none', 'Nic', fn() => []);

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<li class="fa-widgets-alert fa-widgets-tone-danger">', $html);
	Assert::contains('fa-triangle-exclamation', $html);
	Assert::contains('fa-circle-exclamation', $html);
	Assert::contains('<div class="fa-widgets-alert-note">30+ dní</div>', $html);
	Assert::contains('<span class="fa-widgets-alert-value">12</span>', $html);
	Assert::match('%A%<a href="/admin/branch/1" class="fa-widgets-alert-link">Řešit <i%A%', $html);
	Assert::match('%A%<a href="/admin/user" class="fa-widgets-alert-link">[fcadmin.widgets.detail] <i%A%', $html);
	Assert::match('%A%<a href="/admin/alerts" class="fa-widgets-footer-link">Všechna upozornění <i%A%', $html);
	// odkaz s popiskem je v patičce, karta jako celek odkazem není
	Assert::notContains('fa-widgets-link-arrow', $html);
	Assert::contains('[fcadmin.widgets.alerts.empty]', $html);
});

test('widget bez dat ukaze hlasku a duvod, jeho data se nevolaji', function () {
	$called = false;
	$widgetGrid = widgetGrid();
	$widgetGrid->addFigures('economy', 'Hospodaření', function () use (&$called) {
		$called = true;
		return [];
	})->setUnavailable('Náklady poboček v systému nejsou.')->setBadges(function () use (&$called) {
		$called = true;
		return [];
	});
	$widgetGrid->addTiles('tiles', fn() => [], 'Dlaždice')->setUnavailable('');

	$html = renderWidgetGrid($widgetGrid);

	Assert::false($called);
	Assert::contains('<h2 class="fa-widget-title">Hospodaření</h2>', $html);
	Assert::contains('<div class="fa-widgets-unavailable-text">[fcadmin.widgets.unavailable]</div>', $html);
	Assert::contains('<div class="fa-widgets-unavailable-reason">Náklady poboček v systému nejsou.</div>', $html);
	// dlaždice bez dat dostanou kartu, prázdný důvod se nevypíše
	Assert::contains('<h2 class="fa-widget-title">Dlaždice</h2>', $html);
	Assert::same(1, substr_count($html, 'fa-widgets-unavailable-reason'));
});

test('sbalitelny widget je details se summary, kazdy se otevira a zavira zvlast', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addFigures('marketing', 'Marketing', fn() => [new WidgetFigure('Leady', 5)])->setCollapsible()->setLink('/admin/leads');
	$widgetGrid->addFigures('quality', 'Kvalita', fn() => [new WidgetFigure('Reakce', '2 h')])->setCollapsible();

	$html = renderWidgetGrid($widgetGrid);

	// každý se otevírá a zavírá zvlášť - bez atributu name (ten by z nich udělal akordeon)
	Assert::same(2, substr_count($html, '<details class="card fa-widgets-card fa-widgets-collapsible">'));
	Assert::contains('<summary class="fa-widget-header fa-widgets-collapsible-summary">', $html);
	Assert::contains('<span class="fa-widget-title">Marketing</span>', $html);
	// proklik celé karty by zabral i summary - sbalitelný widget ho nemá
	Assert::notContains('stretched-link', $html);
	Assert::contains('Leady', $html);
});

test('obdobi mrizky: widgety bez vlastniho vyberu ho dostanou, vlastni vyber ma prednost, odkazy a hlavicka', function () {
	$received = [];
	$presenter = pagePresenter('dashboard-period=thisYear&dashboard-own-period=today&x=1');
	$widgetGrid = new WidgetGridControl();
	$widgetGrid->setPeriods(WidgetPeriod::THIS_MONTH, [WidgetPeriod::THIS_MONTH, WidgetPeriod::THIS_YEAR, WidgetPeriod::LAST_12_MONTHS])
		->setHeader('Dobrý den', fn(?WidgetPeriod $period) => 'období ' . $period?->value)
		->setHeaderImage('/images/header.webp')
		->setHeaderFullWidth();
	$widgetGrid->addKpi('shared', 'Společné', function (?WidgetPeriod $period) use (&$received) {
		$received['shared'] = $period;
		return 1;
	})->setSubtitle(fn(?WidgetPeriod $period) => $period?->value)
		->setLink(fn(?WidgetPeriod $period) => '/detail?period=' . $period?->value);
	$widgetGrid->addKpi('own', 'Vlastní', function (?WidgetPeriod $period) use (&$received) {
		$received['own'] = $period;
		return 2;
	})->setPeriods(WidgetPeriod::YESTERDAY);
	$presenter->addComponent($widgetGrid, 'dashboard');

	$html = renderWidgetGrid($widgetGrid);

	Assert::same(WidgetPeriod::THIS_YEAR, $received['shared']);
	Assert::same(WidgetPeriod::TODAY, $received['own']);
	// pás přes celou šířku stránky je volba (setHeaderFullWidth), bez ní jen v šířce mřížky
	Assert::contains('<div class="fa-widgets-header fa-widgets-header-image fa-widgets-header-full"', $html);
	Assert::contains("--fa-widgets-header-image: url('/images/header.webp')", html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
	Assert::contains('<h1 class="fa-widgets-header-title">Dobrý den</h1>', $html);
	Assert::contains('<div class="fa-widgets-header-note">období thisYear</div>', $html);
	Assert::contains('<span class="fa-widget-subtitle">thisYear</span>', $html);
	// odkaz jako Closure dostane období widgetu - proklik otevře podrobnosti na stejném období
	Assert::contains('<a href="/detail?period=thisYear" class="stretched-link fa-widgets-link"', $html);
	Assert::match('%A%<select name="dashboard-period" class="form-select form-select-sm" aria-label="[fcadmin.widgets.period.label]" data-widget-grid-period>%A%', $html);
	Assert::contains('<option value="thisYear" selected>[fcadmin.widgets.period.thisYear]</option>', $html);
	Assert::contains('<option value="last12Months">[fcadmin.widgets.period.last12Months]</option>', $html);
	// formulář mřížky nese ostatní parametry, formulář widgetu i období mřížky
	$gridForm = substr($html, 0, strpos($html, '</form>'));
	Assert::contains('<input type="hidden" name="x" value="1">', $gridForm);
	Assert::contains('<input type="hidden" name="dashboard-own-period" value="today">', $gridForm);
	Assert::notContains('type="hidden" name="dashboard-period"', $gridForm);
	Assert::contains('<input type="hidden" name="dashboard-period" value="thisYear">', substr($html, strlen($gridForm)));

	// období mřížky jde do odkazů mřížky, ne widgetů bez vlastního výběru
	$params = [];
	$widgetGrid->saveState($params);
	Assert::same('thisYear', $params['period']);
	Assert::null(linkPeriod($widgetGrid['shared']));

	$widgetGrid->loadState(['period' => 'thisMonth']);
	$params = [];
	$widgetGrid->saveState($params);
	Assert::null($params['period']);
	$widgetGrid->loadState(['period' => 'today']);
	Assert::same(WidgetPeriod::THIS_MONTH, $widgetGrid->getPeriod());
});

test('mrizka bez obdobi a hlavicky hlavicku nevykresli', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addKpi('a', 'A', fn() => 1);

	$html = renderWidgetGrid($widgetGrid);

	Assert::notContains('fa-widgets-header', $html);
	Assert::null($widgetGrid->getPeriod());
});

test('poslednich 12 mesicu: od 1. dne mesice pred 11 mesici do dneska, predchozi o rok driv', function () {
	$now = new DateTimeImmutable('2026-10-20 13:40');
	[$from, $to] = WidgetPeriod::LAST_12_MONTHS->getRange($now);
	Assert::same('2025-11-01 00:00', $from->format('Y-m-d H:i'));
	Assert::same('2026-10-21 00:00', $to->format('Y-m-d H:i'));
	[$from, $to] = WidgetPeriod::LAST_12_MONTHS->getPreviousRange($now);
	Assert::same('2024-11-01', $from->format('Y-m-d'));
	Assert::same('2025-10-21', $to->format('Y-m-d'));
});

test('graf se zvyraznenym pasmem: volba pluginu, spatne zadani vyhodi vyjimku', function () {
	$chart = new WidgetChart('line', ['1/2026', '2/2026', '3/2026'], [['label' => 'Obrat', 'data' => [1, 2, 3]]], highlight: [1, 2]);
	Assert::same([1, 2], $chart->toConfig()['options']['plugins']['fancyadminTheme']['highlight']);
	Assert::false(isset((new WidgetChart('line', ['a'], [['data' => [1]]]))->toConfig()['options']['plugins']));
	Assert::exception(fn() => new WidgetChart('line', ['a'], [['data' => [1]]], highlight: [1]), InvalidArgumentException::class);
});


test('tabulka: skupiny sloupcu, radek Celkem a proklik radku jsou volitelne', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addTable('breakdown', 'Rozpad', [
		'name' => 'Pobočka',
		'sales' => new WidgetTableColumn('Tržba', numeric: true, group: 'Realizované', groupTone: WidgetTone::Success),
		'count' => new WidgetTableColumn('Ks', numeric: true, group: 'Realizované', groupTone: WidgetTone::Success),
		'reserved' => new WidgetTableColumn('Provize', numeric: true, group: 'V rezervacích', groupTone: WidgetTone::Warning),
	], fn() => [
		['id' => 7, 'name' => 'Plzeň', 'sales' => 100, 'count' => 2, 'reserved' => 5],
		['id' => null, 'name' => 'bez pobočky', 'sales' => 10, 'count' => 1, 'reserved' => 0],
	])
		->setTotal(fn() => ['name' => 'Celkem', 'sales' => 110, 'count' => 3, 'reserved' => 5])
		->setRowLink(fn(array $row) => $row['id'] !== null ? '/obrat?branch=' . $row['id'] : null);
	$widgetGrid->addTable('plain', 'Prostá', ['a' => 'A'], fn() => [['a' => 'x']]);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));

	// pás skupin nad záhlavím (sousední sloupce stejné skupiny pod jedním nadpisem, sloupce před první skupinou
	// pod jedním přechodem, poslední skupina sahá přes sloupec šipky),
	// záhlaví je jeden plný řádek; prokliknutelné řádky mají vpravo šipku
	Assert::contains('<tr class="fa-widgets-table-groups"><th colspan="1" class="fa-widgets-table-band fa-widgets-table-band-lead fa-widgets-tone-success" aria-hidden="true"></th><th colspan="2" class="fa-widgets-table-group fa-widgets-tone-success" scope="colgroup">Realizované</th><th colspan="2" class="fa-widgets-table-group fa-widgets-table-group-last fa-widgets-tone-warning" scope="colgroup">V rezervacích</th></tr>', $html);
	Assert::contains('<tr><th scope="col">Pobočka</th><th class="text-end" scope="col">Tržba</th><th class="text-end" scope="col">Ks</th><th class="text-end" scope="col">Provize</th><th class="fa-widgets-table-arrow" aria-hidden="true"></th></tr>', $html);
	Assert::contains('<tr class="fa-widgets-row-linked"><td><a href="/obrat?branch=7" class="stretched-link">Plzeň</a></td>', $html);
	Assert::contains('<td class="fa-widgets-table-arrow"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></td>', $html);
	Assert::contains('<tr><td>bez pobočky</td>', $html);
	Assert::contains('<tfoot><tr class="fa-widgets-table-total"><td>Celkem</td><td class="text-end text-nowrap">110</td>', $html);
	// tabulka bez voleb má hlavičku na jednom řádku a žádnou patičku
	$plain = substr($html, strpos($html, 'data-widget="plain"'));
	Assert::contains('<thead><tr><th scope="col">A</th></tr></thead>', $plain);
	Assert::notContains('<tfoot', $plain);
});

test('vyber obdobi jde vykreslit zvlast do hlavicky stranky, mrizka ho pak nema', function () {
	$presenter = pagePresenter('dashboard-period=thisYear');
	$widgetGrid = new WidgetGridControl();
	$widgetGrid->setPeriods(WidgetPeriod::THIS_MONTH, [WidgetPeriod::THIS_MONTH, WidgetPeriod::THIS_YEAR]);
	$widgetGrid->addKpi('kpi', 'Obrat', fn() => 1);
	$presenter->addComponent($widgetGrid, 'dashboard');

	ob_start();
	$widgetGrid->renderPeriod();
	$period = ob_get_clean();
	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<form class="fa-widgets-period fa-widgets-page-period"', $period);
	Assert::contains('<option value="thisYear" selected>', $period);
	Assert::notContains('data-widget-grid-period', $html);
	Assert::notContains('fa-widgets-header', $html);

	// bez renderPeriod() je výběr v pásu hlavičky jako dřív
	$other = widgetGrid(pagePresenter(''));
	$other->setPeriods(WidgetPeriod::THIS_MONTH, [WidgetPeriod::THIS_MONTH, WidgetPeriod::THIS_YEAR]);
	Assert::contains('<form class="fa-widgets-period fa-widgets-header-period"', renderWidgetGrid($other));
});

test('hlavicka podstranky: drobecky, obdobi v radku, nadpis a podtitul s obdobim', function () {
	$presenter = pagePresenter('dashboard-period=thisYear');
	$widgetGrid = new WidgetGridControl();
	$widgetGrid->setPeriods(WidgetPeriod::THIS_MONTH, [WidgetPeriod::THIS_MONTH, WidgetPeriod::THIS_YEAR])
		->setPage('Rezervace · pobočka Plzeň', fn(?WidgetPeriod $period) => 'období: ' . $period?->value, [
			'Dashboard' => '/admin/',
			'Rezervace' => '/admin/rezervace',
			'Pobočka Plzeň' => null,
		]);
	$widgetGrid->addKpi('kpi', 'Obrat', fn() => 1);
	$presenter->addComponent($widgetGrid, 'dashboard');

	ob_start();
	$widgetGrid->renderPage();
	$page = preg_replace('~>\s+<~', '><', ob_get_clean());
	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<ol class="fa-widgets-breadcrumbs"><li><a href="/admin/">Dashboard</a></li><li><a href="/admin/rezervace">Rezervace</a></li><li aria-current="page">Pobočka Plzeň</li></ol>', $page);
	Assert::contains('<form class="fa-widgets-period fa-widgets-page-period"', $page);
	Assert::contains('<h1 class="fa-widgets-page-title">Rezervace · pobočka Plzeň</h1><span class="fa-widgets-page-subtitle">období: thisYear</span>', $page);
	Assert::notContains('data-widget-grid-period', $html);
});

test('tabulka: vnorene radky (pobocka a jeji makleri jsou skryte pod radkem se sipkou', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addTable('tree', 'Strom', ['name' => 'Pobočka', 'count' => new WidgetTableColumn('Počet', numeric: true)], fn() => [
		['name' => 'Plzeň', 'count' => 2, 'children' => [['name' => 'Adam', 'count' => 1], ['name' => 'Bára', 'count' => 1]]],
		['name' => 'Brno', 'count' => 0, 'children' => []],
	])->setRowChildren(fn(array $row) => $row['children']);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));

	Assert::contains('<tr class="fa-widgets-row-parent" data-widget-row="0"><td><button type="button" class="fa-widgets-row-toggle" aria-expanded="false" data-widget-row-toggle="0"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>Plzeň</td>', $html);
	Assert::same(2, substr_count($html, '<tr class="fa-widgets-row-child" data-widget-row-child="0" hidden>'));
	Assert::contains('<td><span class="fa-widgets-row-dot" aria-hidden="true"></span>Adam</td>', $html);
	Assert::contains('<tr><td>Brno</td>', $html, 'řádek bez dětí nemá šipku');
});

test('sekce, dlazdice s pruhem a panel otevirany odkazem jine karty', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addSection('active', 'Aktivní nabídky')->setSubtitle('stav k 26. 11. 2026')->setText(Nette\Utils\Html::el()->setHtml('<strong>Poměr</strong> = zrušené ÷ nové'));
	$widgetGrid->addKpi('total', 'Aktivní nabídky celkem', fn() => 1342)->setDetail()
		->setSplit([new WidgetSplit('Prodej', 1046, '1 046'), new WidgetSplit('Pronájem', 296)])
		->setToggle('list', 'Seznam nabídek', 'Skrýt seznam');
	$widgetGrid->addTable('list', 'Nabídky starší 90 dní', ['a' => 'A'], fn() => [['a' => 'x']])->setPanel('aging');

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));

	Assert::contains('<h2 class="fa-widgets-section-title">Aktivní nabídky</h2><span class="fa-widgets-section-subtitle">stav k 26. 11. 2026</span>', $html);
	Assert::contains('<p class="fa-widgets-section-text"><strong>Poměr</strong> = zrušené ÷ nové</p>', $html);
	Assert::contains('class="fa-widgets-kpi fa-widgets-kpi-detail"', $html);
	Assert::contains('style="flex-grow: 779"', $html, 'podíl prodeje 1046 / 1342');
	Assert::contains('Prodej <strong>1 046</strong> · 78&nbsp;%', $html);
	Assert::contains('data-widget-toggle="list" aria-expanded="false" data-open-label="Seznam nabídek" data-close-label="Skrýt seznam"', $html);
	Assert::contains('data-widget="list" data-widget-panel="aging" hidden>', $html);
	Assert::contains('<button type="button" class="fa-widgets-panel-close" data-widget-toggle="list">[fcadmin.widgets.panel.close]</button>', $html);
});

test('kompaktni upozorneni: tecka misto ikony, odkazem je radek se sipkou, nad limitem skryte polozky a tlacitko', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addAlerts('stagnation', 'Stagnace', fn() => [
		new WidgetAlert('Novák Jan', WidgetTone::Danger, note: 'pobočka Plzeň', value: '91 dní', link: '/admin/user/1', linkLabel: 'Rezervace makléře'),
		new WidgetAlert('Dvořák Petr', note: 'pobočka Brno', value: '64 dní'),
		new WidgetAlert('Malá Eva', value: '61 dní'),
	])->setCompact()->setLimit(2);
	$widgetGrid->addAlerts('short', 'Do limitu', fn() => [new WidgetAlert('Jediné')])->setCompact()->setLimit(2);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));
	$part = fn(string $name) => substr($html, strpos($html, 'data-widget="' . $name . '"'), 3000);

	Assert::contains('<ul class="fa-widgets-alerts fa-widgets-alerts-compact">', $part('stagnation'));
	Assert::contains('<li class="fa-widgets-alert fa-widgets-tone-danger fa-widgets-alert-linked"><span class="fa-widgets-alert-dot" aria-hidden="true"></span>', $part('stagnation'));
	Assert::contains('<a href="/admin/user/1" class="fa-widgets-alert-text stretched-link" title="Rezervace makléře">Novák Jan</a>', $part('stagnation'));
	Assert::contains('<i class="fa-solid fa-chevron-right fa-widgets-alert-arrow" aria-hidden="true"></i>', $part('stagnation'));
	// v kompaktním seznamu je odkazem řádek, popisek „Zobrazit podrobnosti“ ani ikona tam nejsou
	Assert::notContains('class="fa-widgets-alert-link"', $part('stagnation'));
	Assert::notContains('fa-circle-exclamation', $part('stagnation'));
	Assert::contains('<div class="fa-widgets-alert-text">Dvořák Petr</div>', $part('stagnation'));
	Assert::contains('<li class="fa-widgets-alert fa-widgets-tone-warning fa-widgets-alert-extra" hidden>', $part('stagnation'));
	Assert::same(1, substr_count($part('stagnation'), ' hidden>'));
	Assert::contains('<button type="button" class="fa-widgets-more" data-widget-more aria-expanded="false" data-open-label="[fcadmin.widgets.alerts.more]" data-close-label="[fcadmin.widgets.alerts.less]">[fcadmin.widgets.alerts.more]</button>', $part('stagnation'));
	Assert::notContains('fa-widgets-more', $part('short'));
	Assert::notContains(' hidden>', $part('short'));
});

/** Presenter s routerem - odkazy na sebe sama (přepínače widgetu) */
function linkingPresenter(string $query = ''): DashboardTestPresenter
{
	$factory = new class implements Nette\Application\IPresenterFactory {
		public function getPresenterClass(string &$name): string
		{
			return DashboardTestPresenter::class;
		}

		public function createPresenter(string $name): Nette\Application\IPresenter
		{
			throw new LogicException();
		}
	};
	$presenter = new DashboardTestPresenter();
	$presenter->injectPrimary(new Request(new UrlScript('https://admin.test/admin/?' . $query)), new Response(), $factory, new Nette\Application\Routers\SimpleRouter(), templateFactory: new TemplateFactory(new DashboardLatteFactory()));
	parse_str($query, $params);
	Closure::bind(function () use ($params) {
		$this->setParent(null, 'Test');
		$this->request = new Nette\Application\Request('Test', 'GET', $params + ['action' => 'default']);
		$this->initGlobalParameters();
	}, $presenter, Presenter::class)();

	return $presenter;
}

test('prepinac widgetu: volba z URL, neznama znamena vychozi, do odkazu jen nevychozi', function () {
	$received = [];
	$widgetGrid = widgetGrid(linkingPresenter());
	$chart = null;
	$chart = $widgetGrid->addTable('trend', 'Vývoj', ['a' => 'A'], function () use (&$chart, &$received) {
		$received = [$chart->getSwitch('unit'), $chart->getSwitch('range')];
		return [['a' => 1]];
	})
		->addSwitch('unit', ['ks' => 'ks', 'kc' => 'Kč'])
		->addSwitch('range', ['12' => '12 měsíců', '36' => '3 roky', 'all' => 'Vše'], '12');

	$widgetGrid['trend']->loadState(['switch' => ['unit' => 'kc', 'range' => 'nesmysl']]);
	$html = renderWidgetGrid($widgetGrid);
	Assert::same(['kc', '12'], $received);
	Assert::contains('<div class="fa-widgets-switches"><div class="btn-group btn-group-sm fa-widgets-switch" role="group">', preg_replace('~>\s+<~', '><', $html));
	// zvolená volba je zvýrazněná; odkaz drží ostatní volby, výchozí do adresy nejde
	Assert::match('%A%<a href="%a%" class="btn noajax btn-primary" aria-current="true"%A%data-widget-switch="dashboard-trend-switch[unit]" data-widget-value="kc" data-widget-default="ks"%A%data-widget-redraw="dashboard-trend-redraw">Kč</a>%A%', $html);
	Assert::match('%A%<a href="/admin/?%a%" class="btn noajax btn-outline-primary"%A%data-widget-value="36"%A%>3 roky</a>%A%', $html);
	preg_match('~<a href="([^"]+)"[^>]*data-widget-value="36"~', $html, $link);
	Assert::contains(urlencode('dashboard-trend-switch[unit]') . '=kc', html_entity_decode($link[1]));
	Assert::contains(urlencode('dashboard-trend-switch[range]') . '=36', html_entity_decode($link[1]));
	preg_match('~<a href="([^"]+)"[^>]*data-widget-value="ks"~', $html, $link);
	Assert::notContains('switch', html_entity_decode($link[1]));

	$params = [];
	$widgetGrid['trend']->saveState($params);
	Assert::same(['unit' => 'kc'], $params['switch']);
	Assert::same(['unit' => 'kc'], $chart->getSwitchState());
	Assert::same('dashboard-trend-switch[unit]', $widgetGrid['trend']->getSwitchParameterName('unit'));

	$widgetGrid['trend']->loadState(['switch' => []]);
	$params = [];
	$widgetGrid['trend']->saveState($params);
	Assert::null($params['switch'] ?? null);

	Assert::exception(fn() => $chart->getSwitch('neni'), InvalidArgumentException::class, "%a%'neni'%a%");
	Assert::exception(fn() => $chart->addSwitch('x', []), InvalidArgumentException::class, '%a%žádnou volbu%a%');
	Assert::exception(fn() => $chart->addSwitch('x', ['a' => 'A'], 'b'), InvalidArgumentException::class, "%a%'b'%a%");
});

test('stitky hlavicky podstranky a doplnek rozdeleni ve vlastnim tonu', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->setPage('Rezervace', 'stav k 1. 11. 2026')
		->setPageBadges([new WidgetBadge('zatím není tržba', WidgetTone::Warning, 'Provize nejsou tržbou')]);
	$widgetGrid->addKpi('sale', 'Prodej', fn() => 1)->setSplit([
		new WidgetSplit('Exkluzivní', 2, tone: WidgetTone::Success, note: 'Ø 120 tis. Kč', extra: '14 mil. Kč', extraTone: WidgetTone::Warning),
		new WidgetSplit('Neexkluzivní', 1, tone: WidgetTone::Success, note: 'Ø 110 tis. Kč', extra: '8 mil. Kč'),
	]);

	ob_start();
	$widgetGrid->renderPage();
	$page = ob_get_clean();
	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<span class="fa-widgets-badge fa-widgets-tone-warning fa-widgets-page-badge" title="Provize nejsou tržbou">zatím není tržba</span>', $page);
	Assert::contains('<span class="fa-widgets-split-extra"><span class="fa-widgets-value-tone-warning">14 mil. Kč</span></span>', $html);
	Assert::contains('<span class="fa-widgets-split-extra">8 mil. Kč</span>', $html);
});

test('trychtyr: kroky v ramecku, sipka s prechodem a poznamkou pred krokem; spatny navrat vyjimkou', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addFunnel('funnel', 'Obchodní trychtýř', fn() => [
		new WidgetFunnelStep('Leady', 1554),
		new WidgetFunnelStep('Schůzky', 387, '25 %', WidgetTone::Warning, 'cíl 28 %'),
	]);
	$widgetGrid->addFunnel('empty', 'Prázdný', fn() => []);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));

	Assert::contains('<ol class="fa-widgets-funnel"><li class="fa-widgets-funnel-item"><span class="fa-widgets-funnel-step"><span class="fa-widgets-funnel-label">Leady</span><span class="fa-widgets-funnel-value">1' . "\u{a0}" . '554</span>', $html);
	Assert::contains('<span class="fa-widgets-funnel-arrow fa-widgets-tone-warning"><span class="fa-widgets-funnel-conversion">25 %</span>', $html);
	Assert::contains('<span class="fa-widgets-funnel-note">cíl 28 %</span>', $html);
	Assert::same(1, substr_count($html, 'fa-widgets-funnel-arrow'));
	Assert::contains('[fcadmin.widgets.empty]', $html);

	$widgetGrid = widgetGrid();
	$widgetGrid->addFunnel('bad', 'Špatný', fn() => ['krok']);
	Assert::exception(fn() => renderWidgetGrid($widgetGrid), UnexpectedValueException::class, "%a%'bad'%a%");
});

test('zvyraznena karta: podbarveni tonem, sdeleni, text a polozky s odkazem; null je prazdna', function () {
	$widgetGrid = widgetGrid();
	$widgetGrid->addCallout('problem', 'Kde je problém', fn() => new WidgetCallout('Nabírá dost, ale málo rezervuje', 'Nejslabší přechod: nábory → rezervace.', [
		new WidgetAlert('Brno', WidgetTone::Danger, value: '42 %', link: '/admin/branch/1'),
		new WidgetAlert('Plzeň', value: '45 %'),
	], 'Nejvíce se to týká poboček'));
	$widgetGrid->addCallout('none', 'Nic', fn() => null)->setTone(WidgetTone::Success);

	$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));

	Assert::contains('<div class="card fa-widgets-card fa-widgets-callout fa-widgets-tone-warning">', $html);
	Assert::contains('<div class="card fa-widgets-card fa-widgets-callout fa-widgets-tone-success">', $html);
	Assert::contains('<div class="fa-widgets-callout-headline">Nabírá dost, ale málo rezervuje</div><p class="fa-widgets-callout-text">Nejslabší přechod: nábory → rezervace.</p>', $html);
	Assert::contains('<div class="fa-widgets-callout-items-title">Nejvíce se to týká poboček</div>', $html);
	Assert::contains('<li class="fa-widgets-callout-item fa-widgets-tone-danger fa-widgets-callout-item-linked"><a href="/admin/branch/1" class="fa-widgets-callout-item-text stretched-link">Brno</a><span class="fa-widgets-callout-item-value">42 %</span><i', $html);
	Assert::contains('<span class="fa-widgets-callout-item-text">Plzeň</span>', $html);
	Assert::contains('[fcadmin.widgets.empty]', $html);
});

test('hlavni sekce, sablona bez karty a udaj s proklikem; bez voleb beze zmeny', function () {
	$file = tempnam(sys_get_temp_dir(), 'dashboard') . '.latte';
	file_put_contents($file, '<p class="custom">{$label}</p>');
	$widgetGrid = widgetGrid();
	$widgetGrid->addSection('major', 'Výkonnost')->setSubtitle('realizované provize')->setMajor();
	$widgetGrid->addSection('minor', 'Seřazeno podle poboček');
	$widgetGrid->addTemplate('plain', null, $file, ['label' => 'Přepínač'])->setPlain();
	$widgetGrid->addTemplate('carded', 'V kartě', $file, ['label' => 'Karta']);
	$widgetGrid->addFigures('figures', 'Ekonomika', fn() => [
		new WidgetFigure('Realizované provize', 956, link: '/admin/turnover'),
		new WidgetFigure('Aktivní makléři', 17),
	])->setCards();

	try {
		$html = preg_replace('~>\s+<~', '><', renderWidgetGrid($widgetGrid));
	} finally {
		unlink($file);
	}
	$part = fn(string $name) => substr($html, strpos($html, 'data-widget="' . $name . '"'), 700);

	Assert::contains('<div class="fa-widgets-section fa-widgets-section-major">', $part('major'));
	Assert::contains('<div class="fa-widgets-section">', $part('minor'));
	Assert::notContains('card', substr($part('plain'), 0, strpos($part('plain'), 'Přepínač')));
	Assert::contains('card fa-widgets-card', $part('carded'));
	Assert::contains('<div class="fa-widgets-figure card fa-widgets-card fa-widgets-figure-linked"><div class="fa-widgets-figure-label">Realizované provize<i class="fa-solid fa-chevron-right fa-widgets-link-arrow" aria-hidden="true"></i></div>', $part('figures'));
	Assert::contains('<a href="/admin/turnover" class="stretched-link" aria-label="Realizované provize"></a>', $part('figures'));
	Assert::contains('<div class="fa-widgets-figure card fa-widgets-card"><div class="fa-widgets-figure-label">Aktivní makléři</div>', $part('figures'));
});
