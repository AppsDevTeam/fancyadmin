<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;
use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;
use ADT\FancyAdmin\Model\Entities\Traits\SoftDeleteableInterface;
use ADT\FancyAdmin\Model\Entities\Traits\TreeInterface;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestSoftDeleteableEntity;
use ADT\FancyAdmin\Tests\Fixtures\TestTimestampedEntity;
use ADT\FancyAdmin\Tests\Fixtures\TestTreeNode;
use Tester\Assert;

/**
 * Sdilene entitni traity (Traits/) - casova razitka, autorstvi, aktivita, mekke mazani a strom.
 */

require __DIR__ . '/bootstrap.php';


test('casova razitka se daji nastavit a precist', function () {
	$entity = new TestTimestampedEntity();
	$createdAt = new DateTimeImmutable('2026-01-01 08:00:00');
	$updatedAt = new DateTimeImmutable('2026-02-02 09:30:00');

	Assert::same($entity, $entity->setCreatedAt($createdAt));
	Assert::same($entity, $entity->setUpdatedAt($updatedAt));
	Assert::same($createdAt, $entity->getCreatedAt());
	Assert::same($updatedAt, $entity->getUpdatedAt());
});


test('autorstvi je nepovinne', function () {
	$entity = new TestTimestampedEntity();
	$identity = new TestIdentity();

	Assert::null($entity->getCreatedBy());
	Assert::null($entity->getUpdatedBy());

	$entity->setCreatedBy($identity)->setUpdatedBy($identity);
	Assert::same($identity, $entity->getCreatedBy());
	Assert::same($identity, $entity->getUpdatedBy());

	$entity->setCreatedBy(null)->setUpdatedBy(null);
	Assert::null($entity->getCreatedBy());
	Assert::null($entity->getUpdatedBy());
});


test('entita je ve vychozim stavu aktivni', function () {
	$entity = new TestTimestampedEntity();

	Assert::true($entity->getIsActive());
	Assert::false($entity->setIsActive(false)->getIsActive());
	Assert::true($entity->setIsActive(true)->getIsActive());
});


test('nova entita jeste nema id', function () {
	$entity = new TestTimestampedEntity();

	Assert::null($entity->getId());
	Assert::true($entity->isNew());
});


test('traity plni rozhrani, ktera k nim patri', function () {
	// Projekt dava dohromady traitu a rozhrani sam - test hlida, ze se nerozejdou.
	$entity = new TestTimestampedEntity();

	Assert::type(IsActiveInterface::class, $entity);
	Assert::type(CreatedByNullableInterface::class, $entity);
	Assert::type(UpdatedByInterface::class, $entity);
	Assert::type(SoftDeleteableInterface::class, new TestSoftDeleteableEntity(1));
	Assert::type(TreeInterface::class, new TestTreeNode());
});


test('mekke mazani zaznamena cas i autora', function () {
	$entity = new TestSoftDeleteableEntity(42);
	$deletedAt = new DateTime('2026-05-05 12:00:00');
	$identity = new TestIdentity();

	Assert::false($entity->isDeleted());
	Assert::null($entity->getDeletedAt());
	Assert::null($entity->getDeletedBy());
	Assert::same(0, $entity->getIsDeleted());

	$entity->setDeletedAt($deletedAt)->setDeletedBy($identity)->setIsDeleted();

	Assert::true($entity->isDeleted());
	Assert::same($deletedAt, $entity->getDeletedAt());
	Assert::same($identity, $entity->getDeletedBy());
	// Do sloupce jde id, ne jednicka - unikatni indexy tak plati jen mezi nesmazanymi.
	Assert::same(42, $entity->getIsDeleted());
});


test('smazani jde vzit zpet', function () {
	$entity = new TestSoftDeleteableEntity(7);
	$entity->setDeletedAt(new DateTime())->setIsDeleted();

	$entity->setDeletedAt(null)->setDeletedBy(null);

	Assert::false($entity->isDeleted());
	Assert::null($entity->getDeletedAt());
});


test('bez id se oznacit za smazanou neda', function () {
	// setIsDeleted() uklada do int sloupce getId(), takze u nepersistovane entity spadne.
	$entity = new TestSoftDeleteableEntity();

	Assert::exception(fn() => $entity->setIsDeleted(), TypeError::class);
});


test('strom drzi rodice i potomky', function () {
	$root = new TestTreeNode('root');
	$child = new TestTreeNode('child');

	Assert::null($root->getParent());
	Assert::same([], $root->getChildren());

	$root->addChild($child);

	Assert::same([$child], $root->getChildren());
	Assert::same($root, $child->getParent());
});


test('uzel nemuze byt rodicem sam sobe', function () {
	// Bez teto pojistky by vznikl cyklus a prochazeni stromu by se zacyklilo.
	$node = new TestTreeNode('node');

	$node->setParent($node);

	Assert::null($node->getParent());
});


test('rodic jde odebrat', function () {
	$root = new TestTreeNode('root');
	$child = new TestTreeNode('child');
	$root->addChild($child);

	$child->setParent(null);

	Assert::null($child->getParent());
	// Kolekce potomku je na strane rodice, Doctrine ji synchronizuje az pri nacteni.
	Assert::same([$child], $root->getChildren());
});
