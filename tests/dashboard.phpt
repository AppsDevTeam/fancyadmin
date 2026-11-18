<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControl;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTableColumn;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTile;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTone;
use ADT\FancyAdmin\UI\Presenters\Dashboard;
use Latte\Engine;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\DI\Compiler;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use Tester\Assert;

/**
 * Dashboard (DashboardControl, docs/dashboard.md): mrizka, poradi a sirka widgetu, widgety stat, tiles,
 * table a template, line nacteni dat, tovarna v DI a trait presenteru.
 */

require __DIR__ . '/bootstrap.php';


final class DashboardTranslator implements Translator
{
	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		return '[' . $message . ']';
	}
}


final class DashboardTestPresenter extends Presenter
{
	use Dashboard;

	/** @var callable(DashboardControl): void */
	public $configure;

	protected function configureDashboard(DashboardControl $dashboard): void
	{
		($this->configure)($dashboard);
	}
}


function dashboard(): DashboardControl
{
	$dashboard = new DashboardControl();
	new DashboardTestPresenter()->addComponent($dashboard, 'dashboard');

	return $dashboard;
}


function renderDashboard(DashboardControl $dashboard): string
{
	$dashboard->setTemplateFactory(new TemplateFactory(new class implements LatteFactory {
		public function create(?Nette\Application\UI\Control $control = null): Engine
		{
			$latte = new Engine();
			$latte->setTempDirectory(null);
			return $latte;
		}
	}));
	$dashboard->getTemplate()->setTranslator(new DashboardTranslator());

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
