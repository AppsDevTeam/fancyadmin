<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Doctrine\DBAL\Connection;

/** Spojeni, ktere misto do databaze zapisuje do pole. */
final class TestConnection extends Connection
{
	/** @var list<array{table: string, data: array, types: array}> */
	public array $inserts = [];

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct()
	{
	}

	public function insert(string $table, array $data, array $types = []): int|string
	{
		$this->inserts[] = ['table' => $table, 'data' => $data, 'types' => $types];

		return 1;
	}

	public function getParams(): array
	{
		return ['driver' => 'pdo_sqlite', 'memory' => true];
	}

	/** @return array{table: string, data: array, types: array}|null */
	public function getLastInsert(): ?array
	{
		return $this->inserts ? $this->inserts[array_key_last($this->inserts)] : null;
	}
}
