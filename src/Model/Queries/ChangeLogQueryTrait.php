<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineLoggable\Entity\ChangeLog;
use Doctrine\ORM\QueryBuilder;

trait ChangeLogQueryTrait
{
	public function getEntityClass(): string
	{
		return ChangeLog::class;
	}

	public function byAction(string $action): static
	{
		return $this->by('action', $action);
	}

	public function byObjectClass(string $objectClass): static
	{
		return $this->by('objectClass', $objectClass);
	}

	public function byObjectId(int $objectId): static
	{
		return $this->by('objectId', $objectId);
	}

	public function byIdentityId(int $identityId): static
	{
		return $this->by('identityId', $identityId);
	}

	public function byDateFrom(\DateTimeInterface $from): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($from) {
			$qb->andWhere('e.createdAt >= :dateFrom')
				->setParameter('dateFrom', $from);
		};

		return $this;
	}

	public function byDateTo(\DateTimeInterface $to): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($to) {
			$qb->andWhere('e.createdAt <= :dateTo')
				->setParameter('dateTo', $to);
		};

		return $this;
	}
}
