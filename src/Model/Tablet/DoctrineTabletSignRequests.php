<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Žádosti o podpis v DB - entita projektu (fancyadmin: tablet: signRequestEntity).
 * Bez query objektu a bez bezpečnostních filtrů: ptá se čekací stránka tabletu, na které
 * není přihlášený žádný uživatel.
 */
class DoctrineTabletSignRequests implements TabletSignRequests
{
	/** @param class-string<TabletSignRequest> $entityClass */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly string $entityClass,
	) {
		if (!is_a($entityClass, TabletSignRequest::class, true)) {
			throw new \InvalidArgumentException(sprintf('Entita žádosti o podpis %s musí implementovat %s.', $entityClass, TabletSignRequest::class));
		}
	}

	public function create(Tablet $tablet, string $url, string $title, \DateTimeImmutable $now, \DateTimeImmutable $expiresAt): TabletSignRequest
	{
		return $this->entityClass::create($tablet, $url, $title, $now, $expiresAt);
	}

	public function add(TabletSignRequest $request): void
	{
		$this->em->persist($request);
	}

	public function findPending(Tablet $tablet, \DateTimeImmutable $now): ?TabletSignRequest
	{
		return $this->unfinished($tablet)
			->andWhere('r.openedAt IS NULL')
			->andWhere('r.expiresAt > :now')
			->setParameter('now', $now)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	public function findOpen(Tablet $tablet): ?TabletSignRequest
	{
		return $this->unfinished($tablet)
			->andWhere('r.openedAt IS NOT NULL')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	public function findUnfinished(Tablet $tablet, \DateTimeImmutable $now): array
	{
		return $this->unfinished($tablet)
			->andWhere('r.openedAt IS NOT NULL OR r.expiresAt > :now')
			->setParameter('now', $now)
			->getQuery()
			->getResult();
	}

	private function unfinished(Tablet $tablet): QueryBuilder
	{
		return $this->em->createQueryBuilder()
			->select('r')
			->from($this->entityClass, 'r')
			->andWhere('r.tablet = :tablet')
			->andWhere('r.signedAt IS NULL')
			->andWhere('r.cancelledAt IS NULL')
			->setParameter('tablet', $tablet)
			->orderBy('r.id', 'DESC');
	}
}
