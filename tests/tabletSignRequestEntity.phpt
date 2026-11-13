<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\TabletEntities;

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
use ADT\FancyAdmin\Model\Entities\Tablet as TabletInterface;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest as TabletSignRequestInterface;
use ADT\FancyAdmin\Model\Entities\TabletSignRequestTrait;
use ADT\FancyAdmin\Model\Entities\TabletTrait;
use ADT\FancyAdmin\Model\Tablet\DoctrineTabletSignRequests;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Tester\Assert;

/**
 * Entita žádosti o podpis poslané na tablet (TabletSignRequestTrait, docs/tablets.md):
 * mapování sloupců a vazby na tablet projektu a stav odvozený z časů žádosti.
 */

require __DIR__ . '/bootstrap.php';


// Entity projektu: žádost hledá tablet ve svém jmenném prostoru (targetEntity: 'Tablet')
#[ORM\Entity]
#[ORM\Table(name: 'tablet')]
class Tablet implements TabletInterface
{
	use Identifier;
	use TabletTrait;
}


#[ORM\Entity]
#[ORM\Table(name: 'tablet_sign_request')]
class TabletSignRequest implements TabletSignRequestInterface
{
	use Identifier;
	use TabletSignRequestTrait;
}


function request(DateTimeImmutable $now): TabletSignRequest
{
	return TabletSignRequest::create((new Tablet())->setName('Recepce'), '/klient/formular/1', 'Souhlas', $now, $now->modify('+10 minutes'));
}

$now = new DateTimeImmutable('2026-01-01 12:00:00');


test('mapování: sloupce žádosti a vazba na tablet projektu, smazaný tablet evidenci nemaže', function () {
	$metadata = new ClassMetadata(TabletSignRequest::class);
	$metadata->initializeReflection(new RuntimeReflectionService());
	(new AttributeDriver([]))->loadMetadataForClass(TabletSignRequest::class, $metadata);

	$fields = $metadata->getFieldNames();
	foreach (['id', 'tabletName', 'url', 'title', 'type', 'subjectId', 'data', 'requesterId', 'requesterName', 'createdAt', 'expiresAt', 'openedAt', 'signedAt', 'cancelledAt'] as $field) {
		Assert::contains($field, $fields);
	}
	Assert::same('json', $metadata->getTypeOfField('data'));
	Assert::true($metadata->isNullable('openedAt'));
	Assert::false($metadata->isNullable('expiresAt'));

	$tablet = $metadata->getAssociationMapping('tablet');
	Assert::same(Tablet::class, $tablet->targetEntity);
	Assert::true($tablet->joinColumns[0]->nullable);
	Assert::same('SET NULL', $tablet->joinColumns[0]->onDelete);
});


test('stav žádosti plyne z jejích časů', function () use ($now) {
	$request = request($now);
	Assert::same('Recepce', $request->getTabletName());
	Assert::same(TabletSignRequestState::Pending, $request->getState($now));
	Assert::true($request->isPending($now));
	Assert::false($request->isOpen());

	// nevyzvednutá po konci platnosti
	Assert::same(TabletSignRequestState::Expired, $request->getState($now->modify('+10 minutes')));
	Assert::false($request->isPending($now->modify('+10 minutes')));

	// vyzvednutá už nepropadá, i když se podepisuje dlouho
	$request->markOpened($now->modify('+1 minute'));
	Assert::same(TabletSignRequestState::Opened, $request->getState($now->modify('+1 hour')));
	Assert::true($request->isOpen());

	$request->markSigned($now->modify('+5 minutes'));
	Assert::same(TabletSignRequestState::Signed, $request->getState($now->modify('+1 hour')));
	Assert::false($request->isOpen());

	$cancelled = request($now)->markOpened($now)->cancel($now->modify('+3 minutes'));
	Assert::same(TabletSignRequestState::Cancelled, $cancelled->getState($now->modify('+1 hour')));
	Assert::false($cancelled->isOpen());
});


test('dlouhý název a jméno se zkrátí na délku sloupce', function () use ($now) {
	$request = TabletSignRequest::create((new Tablet())->setName('Recepce'), '/x', str_repeat('á', 300), $now, $now)
		->setRequester('5', str_repeat('š', 300));

	Assert::same(255, mb_strlen($request->getTitle()));
	Assert::same(255, mb_strlen($request->getRequesterName()));
});


test('úložiště odmítne třídu, která není žádost o podpis', function () {
	Assert::exception(
		fn () => new DoctrineTabletSignRequests(new TestEntityManager(), Tablet::class),
		\InvalidArgumentException::class,
	);
});
