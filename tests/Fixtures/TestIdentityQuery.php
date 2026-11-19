<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineAuthenticator\OTP\IdentityQuery as OtpIdentityQuery;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Entities\Enums\IdentityScopeEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Queries\IdentityQuery;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Doctrine\ORM\NonUniqueResultException;
use Generator;
use LogicException;

/**
 * Query nad identitami v pameti. fetchOneOrNull() pri vic vysledcich hazi stejne jako
 * skutecny query object - test tak odhali dotaz, ktery identitu nezuzi dost.
 */
final class TestIdentityQuery implements IdentityQuery, OtpIdentityQuery
{
	/** @var array<string, mixed> */
	private array $criteria = [];

	/** @param list<Identity> $identities */
	public function __construct(private readonly array $identities)
	{
	}

	public function byEmail(string $email): static
	{
		$this->criteria['email'] = $email;
		return $this;
	}

	public function bySsoSub(string $ssoSub): static
	{
		$this->criteria['ssoSub'] = $ssoSub;
		return $this;
	}

	public function byContext(?string $context): static
	{
		$this->criteria['context'] = $context;
		return $this;
	}

	public function byUsername(string $username): static
	{
		$this->criteria['username'] = $username;
		return $this;
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		$this->criteria['phoneNumber'] = $phoneNumber;
		return $this;
	}

	public function disableSecurityFilter(): static
	{
		return $this;
	}

	public function disableAccountFilter(): static
	{
		return $this;
	}

	public function fetch(?int $limit = null): array
	{
		return array_values(array_filter($this->identities, function (Identity $identity): bool {
			foreach ($this->criteria as $property => $value) {
				if ($identity->{'get' . ucfirst($property)}() !== $value) {
					return false;
				}
			}
			return true;
		}));
	}

	public function fetchOneOrNull(bool $strict = true): object|null
	{
		$result = $this->fetch();
		if ($strict && count($result) > 1) {
			throw new NonUniqueResultException();
		}
		return $result[0] ?? null;
	}

	public function fetchOne(bool $strict = true): object
	{
		return $this->fetchOneOrNull($strict) ?? throw new LogicException('Identita nenalezena.');
	}

	public function bySelectedAccount(Account $account): static { throw new LogicException('Nepouziva se.'); }
	public function byEmailOrPhoneNumber(string $email, string $phoneNumber): static { throw new LogicException('Nepouziva se.'); }
	public function byScope(IdentityScopeEnum $scope): static { throw new LogicException('Nepouziva se.'); }
	public function init(): void {}
	public function setSecurityUser(SecurityUser $securityUser): static { return $this; }
	public function byIdNot(int|array $id): static { throw new LogicException('Nepouziva se.'); }
	public function by(array|string $column, mixed $value, QueryObjectByMode $mode = QueryObjectByMode::AUTO): static { throw new LogicException('Nepouziva se.'); }
	public function byId($id): static { throw new LogicException('Nepouziva se.'); }
	public function orderBy(array|string $field, ?string $order = null): static { return $this; }
	public function fetchIterable(): Generator { yield from $this->fetch(); }
	public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array { throw new LogicException('Nepouziva se.'); }
	public function fetchField(string $field): array { throw new LogicException('Nepouziva se.'); }
}


final class TestIdentityQueryFactory implements IdentityQueryFactory
{
	/** @param list<Identity> $identities */
	public function __construct(private readonly array $identities)
	{
	}

	public function create(): OtpIdentityQuery
	{
		return new TestIdentityQuery($this->identities);
	}
}
