<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Enums\GridFilterType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Uložené sestavy gridu (fancyadmin: datagrid: presetEntity) ve stejné tabulce jako pokročilé filtry, vedle
 * GridFilterTrait. Sestava (typ Preset) patří autorovi (createdBy), sdílenou vidí celý tým; pokročilé filtry jsou
 * sdílené vždy. Projekt bez sestav trait nepotřebuje a jeho tabulka nové sloupce nemá.
 */
trait GridFilterPresetTrait
{
	#[ORM\Column(type: 'string', length: 20, nullable: false, enumType: GridFilterType::class, options: ['default' => GridFilterType::Advanced->value])]
	protected GridFilterType $type = GridFilterType::Advanced;

	#[ORM\Column(nullable: false, options: ['default' => true])]
	protected bool $shared = true;

	/** @var array<string, mixed>|null nastavení sloupců sestavy, pokročilý filtr ho nemá */
	#[ORM\Column(type: 'json', nullable: true)]
	protected ?array $columns = null;

	public function getType(): GridFilterType
	{
		return $this->type;
	}

	public function setType(GridFilterType $type): static
	{
		$this->type = $type;
		return $this;
	}

	public function isShared(): bool
	{
		return $this->shared;
	}

	public function setShared(bool $shared): static
	{
		$this->shared = $shared;
		return $this;
	}

	public function getColumns(): ?array
	{
		return $this->columns;
	}

	public function setColumns(?array $columns): static
	{
		$this->columns = $columns;
		return $this;
	}
}
