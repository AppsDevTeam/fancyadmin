<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Entities\Identity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Razitkuje `Identity::$lastLoginAt` pri uspesnem prihlaseni.
 *
 * Navesuje se na `$onAuthEvent` adt/doctrine-authenticatoru:
 *
 * ```neon
 * - '$onAuthEvent[]' = [@ADT\FancyAdmin\Model\Security\LastLoginSubscriber, authEvent]
 * ```
 *
 * Proc z udalosti a ne z prihlasovacich mist: cest dovnitr ma projekt vic
 * (administrace, mobilni aplikace, odkaz s jednorazovym tokenem, SSO) a kazda by na
 * razitko musela myslet zvlast. Udalost je jedine misto, kudy projdou vsechny.
 *
 * Pise se pres DBAL, ne pres ORM: razitko nema co protahnout `flush()` cizi rozdelane
 * zmeny, ktere zrovna visi v entity manageru, a nema smysl ho vlacet identity mapou.
 */
final readonly class LastLoginSubscriber
{
	public function __construct(
		private EntityManagerInterface $em,
	) {
	}

	public function authEvent(
		string $type,
		?string $identity,
		?string $objectClass,
		?string $objectId,
		?int $storageEntityId,
		?string $context,
		?string $reason,
		?array $metadata,
	): void {
		if ($type !== DoctrineAuthenticator::TYPE_LOGIN || $objectId === null) {
			return;
		}

		// Udalost nese i prihlaseni jinych entit nez identity (napr. zarizeni);
		// razitko ma smysl jen na identite.
		if ($objectClass === null || !is_a($objectClass, Identity::class, true)) {
			return;
		}

		$metaData = $this->em->getClassMetadata($objectClass);

		$this->em->getConnection()->update(
			$metaData->getTableName(),
			[$metaData->getColumnName('lastLoginAt') => new DateTimeImmutable()],
			[$metaData->getSingleIdentifierColumnName() => $objectId],
			[Types::DATETIME_IMMUTABLE, Types::INTEGER],
		);
	}
}
