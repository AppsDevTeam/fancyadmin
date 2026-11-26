<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

/**
 * Nastavení sloupců gridu (viditelnost, pořadí, šířka) uložené u přihlášeného uživatele, typicky v databázi.
 * Bez něj ColumnFiltersTrait drží nastavení v session.
 */
interface GridColumnSettingsStorage
{
	/** @return array<string, mixed> */
	public function load(string $grid): array;

	/** @param array<string, mixed> $settings */
	public function save(string $grid, array $settings): void;
}
