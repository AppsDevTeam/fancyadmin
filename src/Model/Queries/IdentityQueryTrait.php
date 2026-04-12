<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use Doctrine\ORM\QueryBuilder;

trait IdentityQueryTrait
{
	public function byEmailOrPhoneNumber(?string $email = null, ?string $phoneNumber = null): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($email, $phoneNumber) {
			if ($email && $phoneNumber) {
				$qb->andWhere('e.email = :email OR e.phoneNumber = :phoneNumber')
					->setParameter('email', $email)
					->setParameter('phoneNumber', $phoneNumber);
			} elseif ($email) {
				$qb->andWhere('e.email = :email')
					->setParameter('email', $email);
			} else {
				$qb->andWhere('e.phoneNumber = :phoneNumber')
					->setParameter('phoneNumber', $phoneNumber);
			}
		};

		return $this;
	}

	public function byUsername(string $username): static
	{
		return $this->by('username', $username);
	}

	public function byEmail(string $email): static
	{
		return $this->by('email', $email);
	}

	public function byEmailNot(string $email): static
	{
		return $this->by('email', $email, QueryObjectByMode::NOT_EQUALS);
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		return $this->by('phoneNumber', $phoneNumber);
	}

	public function bySelectedAccount(Account $account): static
	{
		return $this->by('selectedAccount', $account);
	}

	public function byContext(?string $context): static
	{
		return $this->by('context', $context);
	}

	public function byAllowedResource(string ...$resourceNames): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($resourceNames) {
			$em = $this->getEntityManager();
			$roleClass = $em->findEntityClassByInterface(AclRole::class);
			$profileClass = $em->findEntityClassByInterface(Profile::class);

			$sub = $em->getRepository($roleClass)
				->createQueryBuilder('_role')
				->select('1')
				->leftJoin('_role.acls', '_acl', 'WITH', '_acl.isActive = true')
				->leftJoin('_acl.resource', '_res')
				->andWhere('_role MEMBER OF e.roles OR EXISTS (SELECT 1 FROM ' . $profileClass . ' _prof WHERE _prof.identity = e AND _role MEMBER OF _prof.roles)')
				->andWhere('_role.isAdmin = true OR _res.name IN (:_allowedResourceNames)');

			$qb->andWhere('EXISTS (' . $sub->getDQL() . ')')
				->setParameter('_allowedResourceNames', $resourceNames);
		};

		return $this;
	}
}
