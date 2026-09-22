<?php

declare(strict_types=1);

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Redacted;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\FancyAdmin\Tests\Fixtures\TestChangeLogGrid;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use Tester\Assert;

/**
 * Vykreslovani changesetu v gridu Logy zmen.
 *
 * Grid je jedine misto, kde se serializovany changeset prevadi na neco citelneho - a taky
 * posledni pojistka proti tomu, aby se z auditu daly vycist hashe hesel a tajne klice.
 */

require __DIR__ . '/bootstrap.php';


/** @param list<object> $properties */
function renderChangeSet(TestChangeLogGrid $grid, array $properties): string
{
	$changeSet = new ChangeSet();
	foreach ($properties as $_property) {
		$changeSet->addPropertyChange($_property);
	}

	$changeLog = new ChangeLog();
	$changeLog->setObjectClass('App\Model\Entities\Product');
	$changeLog->setChangeSet($changeSet);

	return (string) (new ReflectionMethod($grid, 'renderChangeSet'))->invoke($grid, $changeLog);
}


test('zmena skalarni hodnoty se vypise jako stara → nova', function () {
	$output = renderChangeSet(new TestChangeLogGrid(new TestTranslator()), [new Scalar('name', 'Kola', 'Limonada')]);

	Assert::contains('Kola', $output);
	Assert::contains('Limonada', $output);
	Assert::contains('→', $output);
});


test('nezmenena property se nevypisuje', function () {
	// stejna stara i nova hodnota neni zmena a nesmi zaplevelovat vypis
	$output = renderChangeSet(new TestChangeLogGrid(new TestTranslator()), [new Scalar('name', 'Kola', 'Kola')]);

	Assert::notContains('Kola', $output);
});


test('vazba se vypise identifikaci, ne id', function () {
	$output = renderChangeSet(new TestChangeLogGrid(new TestTranslator()), [
		new ToOne('category', new Id('1', 'Category', ['name' => 'Napoje']), new Id('2', 'Category', ['name' => 'Jidla'])),
	]);

	Assert::contains('Napoje', $output);
	Assert::contains('Jidla', $output);
});


test('kolekce vypise pridane i odebrane polozky', function () {
	$toMany = new ToMany('variants');
	$toMany->addRemoved(new Id('1', 'Product', ['name' => 'Mala']));
	$toMany->addAdded(new Id('2', 'Product', ['name' => 'Velka']));

	$output = renderChangeSet(new TestChangeLogGrid(new TestTranslator()), [$toMany]);

	Assert::contains('Mala', $output);
	Assert::contains('Velka', $output);
	Assert::contains('−', $output);
	Assert::contains('+', $output);
});


test('vychozi stav nemaskuje nic', function () {
	// co je v danem projektu tajne, vi jen ten projekt - balik sam nehada
	$grid = new TestChangeLogGrid(new TestTranslator());

	Assert::false($grid->getIsMaskedProperty('password'));
	Assert::false($grid->getIsMaskedProperty('name'));
});


test('maskovana property skryje starou i novou hodnotu', function () {
	$grid = new TestChangeLogGrid(new TestTranslator(), ['clientSecret']);

	Assert::true($grid->getIsMaskedProperty('clientSecret'));
	Assert::false($grid->getIsMaskedProperty('name'), 'maskovani se nesmi rozlezt na bezne properties');

	$output = renderChangeSet($grid, [new Scalar('clientSecret', 'stary-secret', 'novy-secret')]);

	Assert::notContains('stary-secret', $output);
	Assert::notContains('novy-secret', $output);
	Assert::contains('clientSecret', $output, 'ze se property zmenila, se ukazat musi');
	Assert::contains('fcadmin.grids.changeLog.masked', $output);
});


test('nezaznamenana hodnota se vypise jako skryta, ne jako prazdny radek', function () {
	// LoggableProperty(withValue: false) - napr. Identity::$password. Hodnota v logu neni,
	// ale "heslo se zmenilo" je prave ta informace, o kterou jde.
	$output = renderChangeSet(new TestChangeLogGrid(new TestTranslator()), [new Redacted('password')]);

	Assert::contains('password', $output);
	Assert::contains('fcadmin.grids.changeLog.masked', $output);
});
