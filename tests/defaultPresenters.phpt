<?php

declare(strict_types=1);

use ADT\FancyAdmin\Core\FancyAdminPresenterFactory;
use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Tests\Fixtures\DefaultPresenters\Backoffice\Presenters\Accounts\AccountsPresenter as ProjectAccountsPresenter;
use ADT\FancyAdmin\UI\Presenters\Defaults;
use Nette\Application\InvalidPresenterException;
use Tester\Assert;

/**
 * Výchozí presentery: presenter projektu podle mapování má přednost, jinak se použije
 * výchozí presenter balíčku; moduly backoffice a zákazníka určují výchozí routy.
 */

require __DIR__ . '/bootstrap.php';


test('mapa výchozích presenterů bere moduly z výchozích rout', function () {
	$map = FancyAdminExtension::defaultPresenterMap(':Admin:Home:', ':Client:Home:');

	Assert::same(Defaults\Portal\SignPresenter::class, $map['Portal:Sign']);
	Assert::same(Defaults\Backoffice\AccountsPresenter::class, $map['Admin:Accounts']);
	Assert::same(Defaults\Customer\ProfilesPresenter::class, $map['Client:Profiles']);
	Assert::false(isset($map['PortalBackoffice:Accounts']));
});


test('každý výchozí presenter jde vytvořit (není abstraktní a je to presenter)', function () {
	foreach (FancyAdminExtension::DEFAULT_PRESENTERS as $presenters) {
		foreach ($presenters as $class) {
			$reflection = new ReflectionClass($class);
			Assert::false($reflection->isAbstract(), $class);
			Assert::true($reflection->isSubclassOf(\Nette\Application\IPresenter::class), $class);
		}
	}
});


test('továrna: presenter projektu má přednost, chybějící nahradí výchozí, neznámý je chyba', function () {
	$factory = new FancyAdminPresenterFactory();
	$factory->setMapping(['PortalBackoffice' => 'ADT\FancyAdmin\Tests\Fixtures\DefaultPresenters\Backoffice\Presenters\**Presenter']);
	$factory->setDefaultPresenters(FancyAdminExtension::defaultPresenterMap(':PortalBackoffice:Home:', ':PortalCustomer:Home:'));

	$name = 'PortalBackoffice:Accounts';
	Assert::same(ProjectAccountsPresenter::class, $factory->getPresenterClass($name));

	$name = 'PortalBackoffice:Identities';
	Assert::same(Defaults\Backoffice\IdentitiesPresenter::class, $factory->getPresenterClass($name));

	Assert::exception(function () use ($factory) {
		$name = 'PortalBackoffice:Unknown';
		$factory->getPresenterClass($name);
	}, InvalidPresenterException::class);
});
