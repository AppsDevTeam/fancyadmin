<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use Doctrine\ORM\QueryBuilder;

trait AclRoleQueryTrait
{
	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}
	
	public function byIsAdmin(bool $isAdmin): static
	{
		return $this->by('isAdmin', $isAdmin);
	}

	public function byType(AclRoleTypeEnum $aclRoleType): static
	{
		return $this->by('type', $aclRoleType);
	}

	public function byContext(?string $context): static
	{
		return $this->by('context', $context);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}

	protected function applySecurityFilter(): void
	{
		$this->filter[static::SECURITY_FILTER] = function (QueryBuilder $qb) {
			if (!$this->securityUser->isLoggedIn()) {
				$qb->andWhere('e.id IS NULL');
				return;
			}

			if (! $this->getSecurityUser()->isAllowedFullDataAclResource()) {
				$qb->andWhere("e.isProfile = 1");
			}
		};
	}

	protected function applyAccountFilter(QueryBuilder $qb, \ADT\FancyAdmin\Model\Entities\Account $account): void
	{
		$qb->andWhere('e.isProfile = 1');
	}
}