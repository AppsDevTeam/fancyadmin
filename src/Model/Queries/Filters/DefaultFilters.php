<?php

namespace ADT\FancyAdmin\Model\Queries\Filters;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Model\Security\SecurityUser;
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

			if (! $this->getSecurityUser()->isAllowedFullDataAclResource()) {
				if ($this->getPrimaryEntityAlias()) {
					if ($this->getPrimaryEntityAlias() !== 'e') {
						$this->innerJoin($qb, 'e.' . trim($this->getPrimaryEntityAlias(), '_'), $this->getPrimaryEntityAlias());
					}
					$this->innerJoin($qb, $this->getPrimaryEntityAlias() . '.account', 'account');

					$qb->andWhere('account IN (:account) OR account.parent IN (:account)');
				} else {
					$qb->andWhere('e IN (:account) OR e.parent IN (:account)');
				}
				$qb->setParameter('account', $this->getSecurityUser()->getIdentity()->getAccounts());
			}
		}, BaseQuery::SECURITY_FILTER);
	}

	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void
	{
		if ($this->getPrimaryEntityAlias()) {
			if ($this->getPrimaryEntityAlias() !== 'e') {
				$this->innerJoin($qb, 'e.' . trim($this->getPrimaryEntityAlias(), '_'), $this->getPrimaryEntityAlias());
			}
			$this->innerJoin($qb, $this->getPrimaryEntityAlias() . '.account', 'account');

			$qb->andWhere('account = :account OR account.parent = :account');
		} else {
			$qb->andWhere('e = :account OR e.parent = :account');
		}
		$qb->setParameter('account', $account);
	}

	public function byAccount(int|Account|array $account): static
	{
		if ($this->getPrimaryEntityAlias() !== 'e') {
			return $this->by($this->getPrimaryEntityAlias() . '.account', $account);
		} else {
			return $this->by('account', $account);
		}
	}
}
