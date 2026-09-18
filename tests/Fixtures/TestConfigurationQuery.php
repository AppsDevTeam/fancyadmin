<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Queries\ConfigurationQuery;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Generator;
use LogicException;

/**
 * Dotaz na konfiguraci nad polem v pameti.
 *
 * Zaznamenava si, ktere filtry se vypnuly - kod, ktery cte konfiguraci nezavisle na
 * prihlasenem uzivateli, je musi vypnout oba, jinak by politiku hesel nenacetl.
 */
final class TestConfigurationQuery implements ConfigurationQuery
{
	public bool $securityFilterDisabled = false;

	public bool $accountFilterDisabled = false;

	public ?string $askedKey = null;

	/** @param array<string, Configuration> $configurations */
	public function __construct(private readonly array $configurations = [])
	{
	}

	public function byKey(string|array $key): static
	{
		$this->askedKey = is_array($key) ? reset($key) : $key;
		return $this;
	}

	public function disableSecurityFilter(): static
	{
		$this->securityFilterDisabled = true;
		return $this;
	}

	public function disableAccountFilter(): static
	{
		$this->accountFilterDisabled = true;
		return $this;
	}

	public function fetchOneOrNull(bool $strict = true): ?object
	{
		return $this->configurations[$this->askedKey] ?? null;
	}

	public function fetchOne(bool $strict = true): object
	{
		return $this->fetchOneOrNull() ?? throw new LogicException('Zaznam nenalezen.');
	}

	public function fetch(?int $limit = null): array
	{
		return array_values($this->configurations);
	}

	public function by(array|string $column, mixed $value, QueryObjectByMode $mode = QueryObjectByMode::AUTO): static
	{
		return $this;
	}

	public function byId($id): static
	{
		return $this;
	}

	public function byIdNot(int|array $id): static
	{
		return $this;
	}

	public function orderBy(array|string $field, ?string $order = null): static
	{
		return $this;
	}

	public function fetchIterable(): Generator
	{
		yield from $this->fetch();
	}

	public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array
	{
		return [];
	}

	public function fetchField(string $field): array
	{
		return [];
	}

	public function init(): void
	{
	}

	public function setSecurityUser(SecurityUser $securityUser): static
	{
		return $this;
	}
}

final class TestConfigurationQueryFactory implements ConfigurationQueryFactory
{
	public ?TestConfigurationQuery $lastQuery = null;

	/** @param array<string, Configuration> $configurations */
	public function __construct(private readonly array $configurations = [])
	{
	}

	public function create(): ConfigurationQuery
	{
		return $this->lastQuery = new TestConfigurationQuery($this->configurations);
	}
}
