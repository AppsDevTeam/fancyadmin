<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestAccountFieldListener;
use ADT\FancyAdmin\Tests\Fixtures\TestAppIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestAppSecurityUser;
use ADT\FancyAdmin\Tests\Fixtures\TestCreatedByEntity;
use ADT\FancyAdmin\Tests\Fixtures\TestCreatedByListener;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestProfile;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\FancyAdmin\Tests\Fixtures\TestSelectAccountListener;
use ADT\FancyAdmin\Tests\Fixtures\TestTimestampedEntity;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Tester\Assert;

/**
 * Doctrine posluchace dodavane balickem.
 */

require __DIR__ . '/bootstrap.php';


function prePersist(object $entity): PrePersistEventArgs
{
	return new PrePersistEventArgs($entity, new TestEntityManager());
}

function preUpdate(object $entity): PreUpdateEventArgs
{
	$changeSet = [];

	return new PreUpdateEventArgs($entity, new TestEntityManager(), $changeSet);
}


test('posluchac autorstvi se veze na persist i update', function () {
	$listener = new TestCreatedByListener(new TestAppSecurityUser());

	Assert::same([Events::prePersist, Events::preUpdate], $listener->getSubscribedEvents());
});


test('autor se doplni prihlasenemu uzivateli', function () {
	$identity = new TestAppIdentity();
	$listener = new TestCreatedByListener(new TestAppSecurityUser($identity));
	$entity = new TestTimestampedEntity();

	$listener->prePersistCallback(prePersist($entity));

	Assert::same($identity, $entity->getCreatedBy());
});


test('neprihlasenemu uzivateli se nepovinny autor nedoplnuje', function () {
	$listener = new TestCreatedByListener(new TestAppSecurityUser(isLoggedIn: false));
	$entity = new TestTimestampedEntity();

	$listener->prePersistCallback(prePersist($entity));

	Assert::null($entity->getCreatedBy());
});


test('u povinneho autora se identita doplnuje vzdy', function () {
	// Entita s traitou CreatedBy sloupec nesmi mit prazdny, takze se nastavuje
	// i bez prihlaseneho uzivatele - a bez nej to skonci chybou uz tady, ne az v databazi.
	$identity = new TestAppIdentity();
	$listener = new TestCreatedByListener(new TestAppSecurityUser($identity, isLoggedIn: false));
	$entity = new TestCreatedByEntity();

	$listener->prePersistCallback(prePersist($entity));

	Assert::same($identity, $entity->getCreatedBy());
});


test('entita bez rozhrani pro autorstvi se preskoci', function () {
	$listener = new TestCreatedByListener(new TestAppSecurityUser(new TestAppIdentity()));

	Assert::noError(fn() => $listener->prePersistCallback(prePersist(new TestAccount('Firma'))));
});


test('autor zmeny se doplni pri update', function () {
	$identity = new TestAppIdentity();
	$entity = new TestCreatedByEntity();
	$listener = new TestCreatedByListener(new TestAppSecurityUser($identity));

	$listener->preUpdateCallback(preUpdate($entity));

	Assert::same($identity, $entity->getUpdatedBy());
});


test('autor zmeny se prepise pri kazde dalsi zmene', function () {
	$prvni = new TestAppIdentity();
	$druhy = new TestAppIdentity();
	$entity = new TestCreatedByEntity();

	new TestCreatedByListener(new TestAppSecurityUser($prvni))->preUpdateCallback(preUpdate($entity));
	new TestCreatedByListener(new TestAppSecurityUser($druhy))->preUpdateCallback(preUpdate($entity));

	Assert::same($druhy, $entity->getUpdatedBy());
});


test('bez prihlaseneho uzivatele se autor zmeny vyprazdni', function () {
	// Sloupec je nullable, takze zmena z konzole nebo z fronty zustane bez autora.
	$entity = new TestCreatedByEntity()->setUpdatedBy(new TestAppIdentity());
	$listener = new TestCreatedByListener(new TestAppSecurityUser(isLoggedIn: false));

	$listener->preUpdateCallback(preUpdate($entity));

	Assert::null($entity->getUpdatedBy());
});


test('entita bez rozhrani pro autora zmeny se preskoci', function () {
	// Rozhrani se musi hledat ve spravnem namespace - bez use by se nazev vyhodnotil
	// v ADT\FancyAdmin\Model\Listeners a podminka by byla vzdy nepravdiva.
	Assert::true(interface_exists(UpdatedByInterface::class));
	Assert::false(interface_exists('ADT\FancyAdmin\Model\Listeners\UpdatedByInterface'));

	$listener = new TestCreatedByListener(new TestAppSecurityUser(new TestAppIdentity()));

	Assert::noError(fn() => $listener->preUpdateCallback(preUpdate(new TestAccount('Firma'))));
});


test('posluchac uctu se veze jen na persist', function () {
	$listener = new TestAccountFieldListener(new TestSecurityUser());

	Assert::same([Events::prePersist], $listener->getSubscribedEvents());
});


test('bez prihlaseneho uzivatele se ucet nedoplnuje', function () {
	$listener = new TestAccountFieldListener(new TestSecurityUser(isLoggedIn: false));

	Assert::noError(fn() => $listener->prePersistCallback(prePersist(new TestAccount('Firma'))));
});


test('entita bez setAccount() se preskoci', function () {
	$listener = new TestAccountFieldListener(new TestSecurityUser(identity: new TestIdentity()));

	Assert::noError(fn() => $listener->prePersistCallback(prePersist(new TestTimestampedEntity())));
});


test('posluchac vyberu uctu se veze na onFlush', function () {
	$listener = new TestSelectAccountListener(new TestSecurityUser(), FancyAdminFactory::create());

	Assert::same([Events::onFlush], $listener->getSubscribedEvents());
});


test('vybira se prvni aktivni profil identity', function () {
	$listener = new TestSelectAccountListener(new TestSecurityUser(), FancyAdminFactory::create());
	$identity = new TestIdentity();
	$neaktivni = new TestProfile($identity, new TestAccount('Neaktivni'))->setIsActive(false);
	$aktivni = new TestProfile($identity, new TestAccount('Aktivni'));

	$method = new ReflectionMethod($listener, 'getFirstActiveProfile');

	Assert::same($aktivni, $method->invoke($listener, $identity));

	$aktivni->setIsActive(false);
	Assert::null($method->invoke($listener, $identity));
});


test('identita bez profilu zadny aktivni profil nema', function () {
	$listener = new TestSelectAccountListener(new TestSecurityUser(), FancyAdminFactory::create());

	Assert::null(new ReflectionMethod($listener, 'getFirstActiveProfile')->invoke($listener, new TestIdentity()));
});
