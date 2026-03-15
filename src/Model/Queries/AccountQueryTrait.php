<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Queries\Filters\DefaultFilters;
use Doctrine\ORM\QueryBuilder;

trait AccountQueryTrait
{
	use DefaultFilters;
	
	public function byIdOrParentId(string|Entity $idOrParentId): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($idOrParentId) {
			$qb->andWhere('e = :idOrParentId OR e.parent = :idOrParentId')
				->setParameter('idOrParentId', $idOrParentId);
		};
		return $this;
	}

	protected function getPrimaryEntityAlias(): ?string
	{
		return null;
	}
	
	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}