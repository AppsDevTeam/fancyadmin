<?php

namespace App\Model\Queries\Filters;

use App\Model\Entities\Branch;
use App\Model\Entities\Company;
use App\Model\Enums\AclResourceEnum;
use App\Model\Queries\Base\BaseQuery;
use App\Model\Security\SecurityUser;
use Doctrine\ORM\QueryBuilder;

trait DefaultFilters
{
	abstract protected function addFilter(callable $callback, ?string $name = null): static;
	abstract public function orderBy(array|string $field, ?string $order = null): static;
	abstract protected function getSecurityUser(): SecurityUser;
	abstract protected function getPrimaryEntityAlias(): ?string;
	abstract protected function innerJoin(QueryBuilder $qb, string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): static;

	public function applySecurityFilter(): void
	{
		$this->addFilter(function (QueryBuilder $qb) {
			if (!$this->getSecurityUser()->isLoggedIn()) {
				$qb->andWhere('e.id IS NULL');
				return;
			}

			if (! $this->getSecurityUser()->isAllowed(AclResourceEnum::ADMIN)) {
				if ($this->getPrimaryEntityAlias()) {
					if ($this->getPrimaryEntityAlias() !== 'e') {
						$this->innerJoin($qb, 'e.' . trim($this->getPrimaryEntityAlias(), '_'), $this->getPrimaryEntityAlias());
					}
					$this->innerJoin($qb, $this->getPrimaryEntityAlias() . '.company', 'company');

					$qb->andWhere('company IN (:company) OR company.parent IN (:company)');
				} else {
					$qb->andWhere('e IN (:company) OR e.parent IN (:company)');
				}
				$qb->setParameter('company', $this->getSecurityUser()->getIdentity()->getCompanies());
			}
		}, BaseQuery::SECURITY_FILTER);
	}

	protected function applyCompanyFilter(QueryBuilder $qb, Company $company): void
	{
		if ($this->getPrimaryEntityAlias()) {
			if ($this->getPrimaryEntityAlias() !== 'e') {
				$this->innerJoin($qb, 'e.' . trim($this->getPrimaryEntityAlias(), '_'), $this->getPrimaryEntityAlias());
			}
			$this->innerJoin($qb, $this->getPrimaryEntityAlias() . '.company', 'company');

			$qb->andWhere('company = :company OR company.parent = :company');
		} else {
			$qb->andWhere('e = :company OR e.parent = :company');
		}
		$qb->setParameter('company', $company);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy([ltrim($this->getPrimaryEntityAlias() . '.', 'e.') . 'createdAt' => 'DESC', 'id' => 'DESC']);
	}

	public function byCompany(int|Company|array $company): static
	{
		if ($this->getPrimaryEntityAlias() !== 'e') {
			return $this->by($this->getPrimaryEntityAlias() . '.company', $company);
		} else {
			return $this->by('company', $company);
		}
	}
}
