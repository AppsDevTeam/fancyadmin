<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

/**
 * Uložené sestavy gridu u přihlášeného uživatele. Vidí vlastní a sdílené sestavy; kdo smí sestavu upravit
 * nebo smazat, rozhoduje úložiště (GridPreset::$editable) a při zakázané změně vyhodí GridPresetException.
 */
interface GridPresetStorage
{
	/** @return list<GridPreset> vlastní sestavy první */
	public function findAll(string $grid): array;

	public function find(string $grid, int $id): ?GridPreset;

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $columns
	 * @throws GridPresetException
	 */
	public function create(string $grid, string $name, bool $shared, array $filters, array $columns): GridPreset;

	/**
	 * Bez filtrů a sloupců změní jen název a viditelnost.
	 *
	 * @param array<string, mixed>|null $filters
	 * @param array<string, mixed>|null $columns
	 * @throws GridPresetException
	 */
	public function update(string $grid, int $id, string $name, bool $shared, ?array $filters = null, ?array $columns = null): GridPreset;

	/** @throws GridPresetException */
	public function delete(string $grid, int $id): void;
}
