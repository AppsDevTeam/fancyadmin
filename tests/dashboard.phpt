<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControl;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardPeriod;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTableColumn;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTile;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTone;
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
 * Dashboard (DashboardControl, docs/dashboard.md): mrizka, poradi a sirka widgetu, widgety stat, tiles,
 * table a template, line nacteni dat, vyber obdobi s prekreslenim jednoho widgetu, DashboardPeriod,
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

	/** @var callable(DashboardControl): void */
	public $configure;

	public bool $ajax = false;

	public function isAjax(): bool
	{
		return $this->ajax;
	}

	protected function configureDashboard(DashboardControl $dashboard): void
	{
		($this->configure)($dashboard);
	}
}


function presenter(string $url = 'https://admin.test/admin/', bool $ajax = false): DashboardTestPresenter
{
	$presenter = new DashboardTestPresenter();
	$presenter->ajax = $ajax;
	$presenter->injectPrimary(new Request(new UrlScript($url)), new Response(), templateFactory: new TemplateFactory(new DashboardLatteFactory()));

	return $presenter;
}


function dashboard(?DashboardTestPresenter $presenter = null): DashboardControl
{
	$dashboard = new DashboardControl();
	($presenter ?? presenter())->addComponent($dashboard, 'dashboard');

	return $dashboard;
}


function renderDashboard(DashboardControl $dashboard): string
{
	ob_start();
	try {
		$dashboard->render();
	} finally {
		$html = ob_get_clean();
	}

	return $html;
}


/** @return list<string> jmena widgetu v poradi vykresleni */
function widgetNames(string $html): array
{
	preg_match_all('~data-dashboard-widget="([^"]+)"~', $html, $match);

	return $match[1];
}


test('mrizka ma pocet sloupcu, widgety jdou podle pozice a sirka se orizne na mrizku', function () {
	$dashboard = dashboard()->setColumns(2);
	$dashboard->addStat('second', 'Druhy', fn() => 2)->setPosition(10);
	$dashboard->addStat('first', 'Prvni', fn() => 1)->setPosition(-1);
	$dashboard->addStat('third', 'Treti', fn() => 3)->setPosition(10)->setSpan(5);

	$html = renderDashboard($dashboard);

	Assert::contains('<div class="fa-dashboard fa-dashboard-cols-2">', $html);
	Assert::same(['first', 'second', 'third'], widgetNames($html));
	Assert::contains('class="fa-dashboard-widget fa-dashboard-widget-stat fa-dashboard-span-2" data-dashboard-widget="third"', $html);
	Assert::contains('class="fa-dashboard-widget fa-dashboard-widget-stat fa-dashboard-span-1" data-dashboard-widget="first"', $html);
});


test('stat vypise formatovane cislo, poznamku, ikonu, ton a odkaz', function () {
	$dashboard = dashboard();
	$dashboard->addStat('pending', 'Čeká <b>na</b> odeslání', fn() => 12345)
		->setNote(fn() => 'objednávek')
		->setIcon('truck')
		->setTone(DashboardTone::Warning)
		->setLink('/admin/order');
	$dashboard->addStat('rate', 'Podíl', fn() => 12.5)->setIcon('fa-regular fa-percent');

	$html = renderDashboard($dashboard);

	Assert::contains('<div class="fa-dashboard-stat fa-dashboard-tone-warning">', $html);
	Assert::contains('<div class="fa-dashboard-stat-value">12' . "\u{a0}" . '345</div>', $html);
	Assert::contains('<div class="fa-dashboard-stat-note">objednávek</div>', $html);
	Assert::contains('<i class="fa-solid fa-truck" aria-hidden="true"></i>', $html);
	Assert::contains('<i class="fa-regular fa-percent" aria-hidden="true"></i>', $html);
	Assert::contains('<div class="fa-dashboard-stat-value">12,50</div>', $html);
	Assert::contains('Čeká &lt;b&gt;na&lt;/b&gt; odeslání', $html);
	Assert::contains('<a href="/admin/order" class="stretched-link"', $html);
	Assert::notContains('fa-dashboard-widget-header', $html);
});


test('dlazdice se vykresli bez karty widgetu, s titulkem jen kdyz ho widget ma', function () {
	$dashboard = dashboard();
	$dashboard->addTiles('kpi', fn() => [
		new DashboardTile('Katalog', 80955, '15 105 v prodeji', 'box'),
		new DashboardTile('Málo skladem', 3, null, 'triangle-exclamation', '/admin/warehouse', DashboardTone::Danger),
	])->setSpan(3);

	$html = renderDashboard($dashboard);

	Assert::same(2, substr_count($html, 'class="card fa-dashboard-tile '));
	Assert::contains('<div class="card fa-dashboard-tile fa-dashboard-tone-danger">', $html);
	Assert::contains('<div class="fa-dashboard-tile-value">80' . "\u{a0}" . '955</div>', $html);
	Assert::contains('15 105 v prodeji', $html);
	Assert::notContains('fa-dashboard-card', $html);
	Assert::notContains('fa-dashboard-widget-header', $html);

	$dashboard = dashboard();
	$dashboard->addTiles('kpi', fn() => ['neni dlazdice']);
	Assert::exception(fn() => renderDashboard($dashboard), UnexpectedValueException::class, "%a%'kpi'%a%");
});


test('tabulka formatuje ciselne sloupce, Html nechava a prazdna hlasi prazdna data', function () {
	$dashboard = dashboard();
	$dashboard->addTable('sales', 'Prodeje', [
		'period' => 'Období',
		'orders' => new DashboardTableColumn('Objednávky', numeric: true),
		'sales' => new DashboardTableColumn('Tržba', numeric: true),
	], fn() => [
		['period' => '<i>Dnes</i>', 'orders' => 1200, 'sales' => Html::el('strong')->setText('5 000 Kč')],
	])->setDescription('Bez stornovaných');
	$dashboard->addTable('empty', 'Prázdná', ['a' => 'A'], fn() => []);

	$html = renderDashboard($dashboard);

	Assert::contains('<h2 class="fa-dashboard-widget-title">Prodeje</h2>', $html);
	Assert::contains('<p class="fa-dashboard-widget-description">Bez stornovaných</p>', $html);
	Assert::contains('<th class="text-end" scope="col">Objednávky</th>', $html);
	Assert::contains('<td>&lt;i&gt;Dnes&lt;/i&gt;</td>', $html);
	Assert::contains('<td class="text-end text-nowrap">1' . "\u{a0}" . '200</td>', $html);
	Assert::contains('<td class="text-end text-nowrap"><strong>5 000 Kč</strong></td>', $html);
	Assert::contains('<p class="fa-dashboard-empty">[fcadmin.dashboard.empty]</p>', $html);
});


test('sablona widgetu dostane parametry, presenter, dashboard i widget', function () {
	$file = tempnam(sys_get_temp_dir(), 'dashboard') . '.latte';
	file_put_contents($file, '<p class="custom">{$label} {$widget->getName()} {$dashboard->getColumns()} {$presenter::class}</p>');

	try {
		$dashboard = dashboard();
		$dashboard->addTemplate('export', 'Export', $file, fn() => ['label' => 'Stáhnout']);

		$html = renderDashboard($dashboard);
	} finally {
		unlink($file);
	}

	Assert::contains('<h2 class="fa-dashboard-widget-title">Export</h2>', $html);
	Assert::contains('<p class="custom">Stáhnout export 3 DashboardTestPresenter</p>', $html);
});


test('data widgetu se nactou az pri vykresleni a jen jednou', function () {
	$calls = 0;
	$dashboard = dashboard();
	$dashboard->addStat('lazy', 'Líný', function () use (&$calls) {
		$calls++;
		return 1;
	});
	$dashboard->addTable('lazyTable', 'Líná', ['a' => 'A'], function () use (&$calls) {
		$calls++;
		return [['a' => 1]];
	});

	Assert::same(0, $calls);
	renderDashboard($dashboard);
	Assert::same(2, $calls);
});


test('neplatne nastaveni skonci vyjimkou', function () {
	$dashboard = dashboard();
	$dashboard->addStat('same', 'A', fn() => 1);

	Assert::exception(fn() => $dashboard->addStat('same', 'B', fn() => 2), InvalidArgumentException::class, "%a%'same'%a%");
	Assert::exception(fn() => $dashboard->addStat('1bad name', 'B', fn() => 2), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->addStat('with-dash', 'B', fn() => 2), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->getWidget('same')->setPeriods(DashboardPeriod::TODAY, [DashboardPeriod::THIS_MONTH]), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->setColumns(0), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->setColumns(7), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->getWidget('same')->setSpan(0), InvalidArgumentException::class);
	Assert::exception(fn() => $dashboard->getWidget('missing'), InvalidArgumentException::class);
});


test('trait presenteru vytvori komponentu dashboard z tovarny a necha ji projektu nastavit', function () {
	$presenter = new DashboardTestPresenter();
	$presenter->configure = fn(DashboardControl $dashboard) => $dashboard->setColumns(4)->addStat('a', 'A', fn() => 1);
	$factory = new class implements DashboardControlFactory {
		public function create(): DashboardControl
		{
			return new DashboardControl();
		}
	};

	$dashboard = $presenter->createComponentDashboard($factory);

	Assert::same(4, $dashboard->getColumns());
	Assert::same(['a'], array_map(fn($widget) => $widget->getName(), $dashboard->getWidgets()));
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

	$definition = $builder->getDefinition('fancyadmin.dashboardControlFactory');
	Assert::type(FactoryDefinition::class, $definition);
	Assert::same(DashboardControlFactory::class, $definition->getImplement());
	Assert::same(DashboardControl::class, $definition->getResultDefinition()->getFactory()->getEntity());
});


test('vyber obdobi: data dostanou zvolene obdobi, neplatne a nenabizene je vychozi, vychozi nejde do odkazu', function () {
	$received = [];
	$dashboard = dashboard();
	$dashboard->addTable('sales', 'Prodeje', ['a' => 'A'], function (?DashboardPeriod $period) use (&$received) {
		$received[] = $period;
		return [];
	})->setPeriods(DashboardPeriod::TODAY, [DashboardPeriod::TODAY, DashboardPeriod::THIS_MONTH]);
	$control = $dashboard['sales'];

	$control->loadState(['period' => 'thisMonth']);
	Assert::same(DashboardPeriod::THIS_MONTH, $dashboard->getWidget('sales')->getPeriod());
	Assert::same('thisMonth', $control->period);

	foreach (['nesmysl', 'thisYear', 'today'] as $value) {
		$control->loadState(['period' => $value]);
		Assert::same(DashboardPeriod::TODAY, $dashboard->getWidget('sales')->getPeriod(), $value);
		Assert::null($control->period, $value);
	}

	$control->loadState(['period' => 'thisMonth']);
	renderDashboard($dashboard);
	Assert::same([DashboardPeriod::THIS_MONTH], $received);
});


test('widget bez obdobi nema vyber a jeho data dostanou null', function () {
	$received = 'nevolano';
	$dashboard = dashboard();
	$dashboard->addStat('pending', 'Čeká', function ($period) use (&$received) {
		$received = $period;
		return 1;
	});
	$dashboard['pending']->loadState(['period' => 'today']);

	$html = renderDashboard($dashboard);

	Assert::null($received);
	Assert::null($dashboard['pending']->period);
	Assert::notContains('fa-dashboard-period', $html);
});


test('formular obdobi: select s volbami, skryta pole ostatnich parametru stranky a noscript odeslani', function () {
	$dashboard = dashboard(presenter('https://admin.test/admin/?dashboard-orders-period=lastMonth&dashboard-sales-period=today&dashboard-sales-redraw=1&filter%5Bstate%5D=2'));
	$dashboard->addTable('sales', 'Prodeje', ['a' => 'A'], fn() => [])->setPeriods(DashboardPeriod::THIS_MONTH, [DashboardPeriod::TODAY, DashboardPeriod::THIS_MONTH]);
	$dashboard['sales']->loadState(['period' => 'today']);

	$html = renderDashboard($dashboard);

	Assert::contains('<form class="fa-dashboard-period" method="get" action="/admin/">', $html);
	Assert::contains('<input type="hidden" name="dashboard-orders-period" value="lastMonth">', $html);
	Assert::contains('<input type="hidden" name="filter[state]" value="2">', $html);
	Assert::notContains('type="hidden" name="dashboard-sales-period"', $html);
	Assert::notContains('redraw" value', $html);
	Assert::match('%A%<select name="dashboard-sales-period" class="form-select form-select-sm" aria-label="[fcadmin.dashboard.period.label]"%A%data-dashboard-period data-dashboard-redraw="dashboard-sales-redraw">%A%', $html);
	Assert::contains('<option value="today" selected>[fcadmin.dashboard.period.today]</option>', $html);
	Assert::contains('<option value="thisMonth">[fcadmin.dashboard.period.thisMonth]</option>', $html);
	Assert::notContains('thisYear', $html);
	Assert::contains('<noscript><button type="submit" class="btn btn-sm btn-outline-secondary">[fcadmin.dashboard.period.submit]</button></noscript>', $html);
});


test('ajax s redraw prekresli jen snippet toho widgetu a data ostatnich se nenactou', function () {
	$calls = [];
	$dashboard = dashboard(presenter(ajax: true));
	foreach (['sales', 'orders'] as $name) {
		$dashboard->addTable($name, $name, ['a' => 'A'], function (?DashboardPeriod $period) use (&$calls, $name) {
			$calls[] = $name;
			return [['a' => $period->value]];
		})->setPeriods(DashboardPeriod::TODAY);
	}
	$dashboard->addStat('pending', 'Čeká', function () use (&$calls) {
		$calls[] = 'pending';
		return 1;
	});

	$dashboard['orders']->loadState(['period' => 'yesterday']);
	$dashboard['sales']->loadState(['period' => 'lastMonth', 'redraw' => '1']);
	Assert::true($dashboard['sales']->isControlInvalid('body'));
	Assert::false($dashboard['orders']->isControlInvalid());

	$dashboard->snippetMode = true;
	$output = renderDashboard($dashboard);

	$snippets = (array) $dashboard->getPresenter()->getPayload()->snippets;
	Assert::same(['snippet-dashboard-sales-body'], array_keys($snippets));
	Assert::contains('lastMonth', $snippets['snippet-dashboard-sales-body']);
	Assert::same(['sales'], $calls);
	Assert::same('', trim($output));
});


test('redraw bez ajaxu nic neprekresluje (cela stranka)', function () {
	$dashboard = dashboard(presenter(ajax: false));
	$dashboard->addTable('sales', 'Prodeje', ['a' => 'A'], fn() => [])->setPeriods(DashboardPeriod::TODAY);
	$dashboard['sales']->loadState(['redraw' => '1']);

	Assert::false($dashboard['sales']->isControlInvalid());
});


/** @return array{string, string} */
function periodRange(DashboardPeriod $period, string $now, bool $previous = false): array
{
	$range = $previous ? $period->getPreviousRange(new DateTimeImmutable($now)) : $period->getRange(new DateTimeImmutable($now));

	return [$range[0]->format('Y-m-d H:i'), $range[1]->format('Y-m-d H:i')];
}


test('DashboardPeriod: rozsah vsech obdobi, konec vylucny, tyden od pondeli', function () {
	$now = '2026-11-18 14:35'; // streda
	Assert::same(['2026-11-18 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::TODAY, $now));
	Assert::same(['2026-11-17 00:00', '2026-11-18 00:00'], periodRange(DashboardPeriod::YESTERDAY, $now));
	Assert::same(['2026-11-16 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::THIS_WEEK, $now));
	Assert::same(['2026-11-12 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::LAST_7_DAYS, $now));
	Assert::same(['2026-11-01 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::THIS_MONTH, $now));
	Assert::same(['2026-10-01 00:00', '2026-11-01 00:00'], periodRange(DashboardPeriod::LAST_MONTH, $now));
	Assert::same(['2026-10-01 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::THIS_QUARTER, $now));
	Assert::same(['2026-01-01 00:00', '2026-11-19 00:00'], periodRange(DashboardPeriod::THIS_YEAR, $now));

	Assert::same(['2026-11-16 00:00', '2026-11-17 00:00'], periodRange(DashboardPeriod::THIS_WEEK, '2026-11-16 08:00'), 'v pondeli jen dnesek');
	Assert::same(['2026-12-01 00:00', '2027-01-01 00:00'], periodRange(DashboardPeriod::LAST_MONTH, '2027-01-05'), 'minuly mesic pres rok');
	Assert::same(['2027-01-01 00:00', '2027-01-06 00:00'], periodRange(DashboardPeriod::THIS_QUARTER, '2027-01-05'));
	Assert::same(['2026-07-01 00:00', '2026-09-30 00:00'], periodRange(DashboardPeriod::THIS_QUARTER, '2026-09-29 23:59'));
});


test('DashboardPeriod: predchozi obdobi je stejne dlouhe a nepresahuje do zvoleneho', function () {
	$now = '2026-11-18 14:35';
	Assert::same(['2026-11-17 00:00', '2026-11-18 00:00'], periodRange(DashboardPeriod::TODAY, $now, true));
	Assert::same(['2026-11-16 00:00', '2026-11-17 00:00'], periodRange(DashboardPeriod::YESTERDAY, $now, true));
	Assert::same(['2026-11-09 00:00', '2026-11-12 00:00'], periodRange(DashboardPeriod::THIS_WEEK, $now, true));
	Assert::same(['2026-11-05 00:00', '2026-11-12 00:00'], periodRange(DashboardPeriod::LAST_7_DAYS, $now, true));
	Assert::same(['2026-10-01 00:00', '2026-10-19 00:00'], periodRange(DashboardPeriod::THIS_MONTH, $now, true));
	Assert::same(['2026-09-01 00:00', '2026-10-01 00:00'], periodRange(DashboardPeriod::LAST_MONTH, $now, true));
	Assert::same(['2026-07-01 00:00', '2026-08-19 00:00'], periodRange(DashboardPeriod::THIS_QUARTER, $now, true));
	Assert::same(['2025-01-01 00:00', '2025-11-19 00:00'], periodRange(DashboardPeriod::THIS_YEAR, $now, true));

	Assert::same(['2026-02-01 00:00', '2026-03-01 00:00'], periodRange(DashboardPeriod::THIS_MONTH, '2026-03-31', true), 'delsi nez unor se zkrati na jeho konec');
	Assert::same(['2026-01-01 00:00', '2026-02-01 00:00'], periodRange(DashboardPeriod::LAST_MONTH, '2026-03-10', true), 'minuly mesic (unor) proti celemu lednu');
	Assert::same(['2026-12-01 00:00', '2026-12-06 00:00'], periodRange(DashboardPeriod::THIS_MONTH, '2027-01-05', true), 'pres prelom roku');
	Assert::same(['2027-01-01 00:00', '2028-01-01 00:00'], periodRange(DashboardPeriod::THIS_YEAR, '2028-12-31', true), 'prestupny rok proti celemu predchozimu');
	Assert::same(['2026-12-31 00:00', '2027-01-01 00:00'], periodRange(DashboardPeriod::TODAY, '2027-01-01 00:00', true));
});


test('DashboardPeriod: hodnota z URL a klic prekladu', function () {
	Assert::same(DashboardPeriod::LAST_MONTH, DashboardPeriod::fromUrl('lastMonth', DashboardPeriod::TODAY));
	Assert::same(DashboardPeriod::TODAY, DashboardPeriod::fromUrl('nesmysl', DashboardPeriod::TODAY));
	Assert::same(DashboardPeriod::TODAY, DashboardPeriod::fromUrl(null, DashboardPeriod::TODAY));
	Assert::same('fcadmin.dashboard.period.last7Days', DashboardPeriod::LAST_7_DAYS->getTranslationKey());
});
