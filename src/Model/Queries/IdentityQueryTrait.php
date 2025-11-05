<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use Doctrine\ORM\QueryBuilder;

trait IdentityQueryTrait
{
	public function byEmailOrPhoneNumber(string $email, string $phoneNumber): static
	{
		$this->filter[] = function (QueryBuilder $qb) use ($email, $phoneNumber) {
			$qb->andWhere('e.email = :email OR e.phoneNumber = :phoneNumber')
				->setParameter('email', $email)
				->setParameter('phoneNumber', $phoneNumber);
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
}
