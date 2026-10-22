<?php

declare(strict_types=1);

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Security\LastLoginSubscriber;
use ADT\FancyAdmin\Tests\Fixtures\TestConnection;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use Tester\Assert;

/**
 * LastLoginSubscriber - razitko posledniho prihlaseni na identite.
 *
 * Razitkuje se z autentizacni udalosti, protoze cest dovnitr ma projekt vic a udalost
 * je jedine misto, kudy projdou vsechny. Pise se pres DBAL, aby razitko neprotahlo
 * flush() cizi rozdelane zmeny.
 */

require __DIR__ . '/bootstrap.php';

/** @return array{0: LastLoginSubscriber, 1: TestConnection} */
function createSubscriber(): array
{
	$connection = new TestConnection();
	$em = new TestEntityManager([TestIdentity::class], $connection);

	$metadata = $em->getClassMetadata(TestIdentity::class);
	$metadata->setPrimaryTable(['name' => 'identity']);
	$metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'columnName' => 'id', 'id' => true]);
	$metadata->mapField(['fieldName' => 'lastLoginAt', 'type' => 'datetime_immutable', 'columnName' => 'last_login_at']);

	return [new LastLoginSubscriber($em), $connection];
}

function dispatch(LastLoginSubscriber $subscriber, string $type, ?string $objectClass, ?string $objectId): void
{
	$subscriber->authEvent($type, 'user@example.com', $objectClass, $objectId, null, null, null, null);
}

test('prihlaseni orazitkuje spravny radek', function () {
	[$subscriber, $connection] = createSubscriber();

	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN, TestIdentity::class, '42');

	$update = $connection->getLastUpdate();

	Assert::notNull($update);
	Assert::same('identity', $update['table']);
	Assert::same(['id' => '42'], $update['criteria']);
	Assert::type(DateTimeImmutable::class, $update['data']['last_login_at']);
});

// Jinak by ucet vypadal pouzivane i ve chvili, kdy se na nej nekdo jen snazi dostat.
test('razitko posouva jen uspesne prihlaseni', function () {
	[$subscriber, $connection] = createSubscriber();

	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN_FAILED, TestIdentity::class, '42');
	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN_BLOCKED, TestIdentity::class, '42');
	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGOUT, TestIdentity::class, '42');

	Assert::same([], $connection->updates);
});

// Sloupec je na identite, jina entita (napr. zarizeni) ho nema kam dostat.
test('prihlaseni jine entity nez identity se ignoruje', function () {
	[$subscriber, $connection] = createSubscriber();

	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN, stdClass::class, '42');
	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN, null, '42');
	dispatch($subscriber, DoctrineAuthenticator::TYPE_LOGIN, TestIdentity::class, null);

	Assert::same([], $connection->updates);
});
