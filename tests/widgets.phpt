<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetChart;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetPeriod;
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
 * Dashboard (WidgetGridControl, docs/widgets.md): mrizka, poradi a sirka widgetu, widgety stat, tiles,
 * table, template a chart, line nacteni dat, vyber obdobi s prekreslenim jednoho widgetu, WidgetPeriod,
 * tovarna v DI a trait presenteru.
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
	])->setDescription('Bez stornovaných');
	$widgetGrid->addTable('empty', 'Prázdná', ['a' => 'A'], fn() => []);

	$html = renderWidgetGrid($widgetGrid);

	Assert::contains('<h2 class="fa-widget-title">Prodeje</h2>', $html);
	Assert::contains('<p class="fa-widget-description">Bez stornovaných</p>', $html);
	Assert::contains('<th class="text-end" scope="col">Objednávky</th>', $html);
	Assert::contains('<td>&lt;i&gt;Dnes&lt;/i&gt;</td>', $html);
	Assert::contains('<td class="text-end text-nowrap">1' . "\u{a0}" . '200</td>', $html);
	Assert::contains('<td class="text-end text-nowrap"><strong>5 000 Kč</strong></td>', $html);
	Assert::contains('<p class="fa-widgets-empty">[fcadmin.widgets.empty]</p>', $html);
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
	Assert::exception(fn() => $widgetGrid->setColumns(7), InvalidArgumentException::class);
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
	Assert::same('thisMonth', $control->period);

	foreach (['nesmysl', 'thisYear', 'today'] as $value) {
		$control->loadState(['period' => $value]);
		Assert::same(WidgetPeriod::TODAY, $widgetGrid->getWidget('sales')->getPeriod(), $value);
		Assert::null($control->period, $value);
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
	Assert::null($widgetGrid['pending']->period);
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
	Assert::same(1, preg_match("~<canvas data-adt-chart='([^']+)' role=\"img\" aria-label=\"([^\"]*)\"></canvas>~", $html, $match));

	return json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
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
