<?php

declare(strict_types=1);

use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Model\Entities;
use ADT\FancyAdmin\Model\Queries\Defaults;
use ADT\FancyAdmin\Model\Queries\Factories;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestConfiguration;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestGridFilter;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\PhpGenerator;
use Tester\Assert;

/**
 * Výchozí query balíčku: projekt bez vlastní query a továrny dostane třídu z balíčku
 * napojenou na svou entitu, vlastní továrna projektu má přednost.
 */

require __DIR__ . '/bootstrap.php';


test('každá výchozí query jde načíst a implementuje rozhraní své továrny', function () {
	foreach (FancyAdminExtension::DEFAULT_QUERIES as $factory => [$query]) {
		$returnType = (string) new ReflectionMethod($factory, 'create')->getReturnType();
		Assert::true(is_a($query, $returnType, true), "$query implementuje $returnType");
	}
});


test('plán: bez továrny projektu a s jednou entitou se výchozí query registruje', function () {
	$plan = FancyAdminExtension::planDefaultQueries(
		static fn(string $factory): bool => $factory === Factories\IdentityQueryFactory::class,
		static fn(string $interface): array => match ($interface) {
			Entities\Account::class => [TestAccount::class],
			Entities\Configuration::class => [TestConfiguration::class],
			default => [],
		},
	);

	Assert::same([Defaults\AccountQuery::class, TestAccount::class], $plan[Factories\AccountQueryFactory::class]);
	Assert::same([Defaults\ConfigurationQuery::class, TestConfiguration::class], $plan[Factories\ConfigurationQueryFactory::class]);
	// entitu určuje trait, na entitách projektu nezávisí
	Assert::same([Defaults\ChangeLogQuery::class, null], $plan[Factories\ChangeLogQueryFactory::class]);
	// vlastní továrna projektu má přednost
	Assert::false(isset($plan[Factories\IdentityQueryFactory::class]));
	// bez entity projekt funkci nepoužívá
	Assert::false(isset($plan[Factories\TabletQueryFactory::class]));
});


test('plán: dvě entity se stejným rozhraním jsou chyba, ne náhodná volba', function () {
	Assert::exception(
		fn() => FancyAdminExtension::planDefaultQueries(
			static fn(): bool => false,
			static fn(string $interface): array => $interface === Entities\Account::class ? ['App\A', 'App\B'] : [],
		),
		RuntimeException::class,
		'%a%Entities\Account implementují App\A, App\B%a%',
	);
});


test('kontejner: továrny vytvoří výchozí query s entitou projektu a uživatelem', function () {
	$builder = new ContainerBuilder();
	$builder->addDefinition('em')->setType(EntityManagerInterface::class)->setFactory(TestEntityManager::class);
	$builder->addDefinition('securityUser')->setType(SecurityUser::class)->setFactory(TestSecurityUser::class);
	FancyAdminExtension::addDefaultQueryDefinitions($builder, 'fancyadmin.defaultQuery', [
		Factories\AccountQueryFactory::class => [Defaults\AccountQuery::class, TestAccount::class],
		GridFilterQueryFactory::class => [Defaults\GridFilterQuery::class, TestGridFilter::class],
		Factories\ChangeLogQueryFactory::class => [Defaults\ChangeLogQuery::class, null],
	]);

	$container = compileContainer($builder);

	$account = $container->getByType(Factories\AccountQueryFactory::class)->create();
	Assert::type(Defaults\AccountQuery::class, $account);
	Assert::same(TestAccount::class, $account->getEntityClass());

	$gridFilter = $container->getByType(GridFilterQueryFactory::class)->create();
	Assert::type(Defaults\GridFilterQuery::class, $gridFilter);
	Assert::same(TestGridFilter::class, $gridFilter->getEntityClass());

	$changeLog = $container->getByType(Factories\ChangeLogQueryFactory::class)->create();
	Assert::same(\ADT\DoctrineLoggable\Entity\ChangeLog::class, $changeLog->getEntityClass());
});


function compileContainer(ContainerBuilder $builder): Container
{
	$builder->complete();
	$class = 'DefaultQueriesContainer' . md5(uniqid('', true));
	$generator = new PhpGenerator($builder);
	$classType = $generator->generate($class);
	eval($generator->toString($classType));
	return new $class;
}
