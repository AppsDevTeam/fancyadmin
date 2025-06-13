<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObjectByMode;
use App\Model\Entities\Company;
use Doctrine\ORM\QueryBuilder;

trait OnetimeTokenQueryTrait
{
	public function applySecurityFilter(): void
	{
	}

	protected function setDefaultOrder(): void
	{
	}

	public function byToken(string $token): static
	{
		return $this->by('token', $token);
	}

	public function byType(string $type): static
	{
		return $this->by('type', $type);
	}

	public function byObjectId(int|array $objectId): static
	{
		return $this->by('objectId', $objectId);
	}

	public function byIsValid(bool $checkValidUntil = true): static
	{
		if ($checkValidUntil) {
			$this->filter[] = function (QueryBuilder $qb) {
				$qb->andWhere('e.validUntil >= :now')
					->andWhere('e.usedAt IS NULL')
					->setParameter('now', new \DateTimeImmutable());
			};
			return $this;
		}

		return $this->by('usedAt', null);
	}

	public function byIpAddress(string $ipAddress): static
	{
		return $this->by('ipAddress', $ipAddress);
	}

	public function byCreatedBetween(?\DateTimeImmutable $from = null, \DateTimeImmutable $to = null): static
	{
		return $this->by('createdAt', [$from, $to], QueryObjectByMode::BETWEEN);
	}
}