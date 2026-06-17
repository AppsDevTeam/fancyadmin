<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilterTrait;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Queries\Filters\DefaultFilters;
use Doctrine\ORM\QueryBuilder;

trait ProfileQueryTrait
{
	use DefaultFilters;
	use IsActiveFilterTrait;

	public function byRole(int|array|AclRole $roles): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($roles) {
			$this->innerJoin($qb, 'e.roles', 'roles');

			$qb->andWhere('roles IN (:roles)');
			$qb->setParameter('roles', $roles);
		};
		return $this;
	}

	public function setDefaultOrder(): void
	{
		$this->orderBy(['identity.firstName' => 'ASC', 'identity.lastName' => 'ASC', 'id' => 'ASC']);
	}

	protected function getPrimaryEntityAlias(): string
	{
		return 'e';
	}
}