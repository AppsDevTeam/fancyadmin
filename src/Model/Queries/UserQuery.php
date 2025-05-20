<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\IAclRole;
use ADT\FancyAdmin\Model\Entities\IUser;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends \ADT\DoctrineComponents\QueryObject<IUser>
 */
trait UserQuery
{
	const string ROLE_ALIAS = 'r';

	public function setDefaultOrder(): void
	{
		$this->orderBy(['firstName' => 'ASC', 'lastName' => 'ASC']);
	}

	public function byEmail(string $email): static
	{
		return $this->by('email', $email);
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		return $this->by('phoneNumber', $phoneNumber);
	}

	protected function joinAclRole(): static
	{
		$this->filter[static::ROLE_ALIAS] = function (QueryBuilder $qb) {
			$qb->leftJoin('e.roles', static::ROLE_ALIAS);
		};
		return $this;
	}

	public function byAclRoleName(string|array|null $role): static
	{
		if ($role) {
			$this->joinAclRole();
			$this->filter[] = function (QueryBuilder $qb) use ($role) {
				$qb->andWhere(static::ROLE_ALIAS . '.name IN (:roleName)')
					->setParameter('roleName', $role);
			};
		}
		return $this;
	}

	public function byAclRole(string|array|IAclRole|null $role): static
	{
		if ($role) {
			$this->joinAclRole();
			$this->filter[] = function (QueryBuilder $qb) use ($role) {
				$role = $role instanceof IAclRole ? [$role->getName()] : ((array) $role);

				$qb->andWhere(static::ROLE_ALIAS . '.name IN (:roles)')
					->setParameter('roles', $role);
			};
		}
		return $this;
	}
}
