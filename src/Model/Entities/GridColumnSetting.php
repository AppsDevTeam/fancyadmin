<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

/** Nastavení sloupců gridu u uživatele (viditelnost, pořadí, šířka z dialogu Sloupce). */
interface GridColumnSetting extends Entity
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;

	public function getGrid(): string;
	public function setGrid(string $grid): static;

	/** @return array<string, mixed> */
	public function getSettings(): array;
	/** @param array<string, mixed> $settings */
	public function setSettings(array $settings): static;
}
