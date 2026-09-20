<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineAuthenticator\AuthLog;
use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;

trait AuthLogQueryTrait
{
	public function getEntityClass(): string
	{
		return AuthLog::class;
	}

	public function byType(string $type): static
	{
		return $this->by('type', $type);
	}

	public function byIdentity(string $identity): static
	{
		return $this->by('identity', $identity);
	}

	public function byObjectId(string $objectId): static
	{
		return $this->by('objectId', $objectId);
	}

	public function byIp(string $ip): static
	{
		return $this->by('ip', $ip);
	}

	public function byDateFrom(DateTimeInterface $from): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($from) {
			$qb->andWhere('e.createdAt >= :dateFrom')
				->setParameter('dateFrom', $from);
		};

		return $this;
	}

	public function byDateTo(DateTimeInterface $to): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($to) {
			$qb->andWhere('e.createdAt <= :dateTo')
				->setParameter('dateTo', $to);
		};

		return $this;
	}
}
