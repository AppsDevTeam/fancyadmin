<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

/** Unikátní omezení doplní entitě TraitIndexListener z adt/doctrine-components. */
#[UniqueConstraint(fields: ['identity', 'grid'])]
trait GridColumnSettingTrait
{
	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected Identity $identity;

	#[ORM\Column(nullable: false)]
	protected string $grid;

	/** @var array<string, mixed> */
	#[ORM\Column(type: 'json', nullable: false)]
	protected array $settings = [];

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): static
	{
		$this->grid = $grid;
		return $this;
	}

	public function getSettings(): array
	{
		return $this->settings;
	}

	public function setSettings(array $settings): static
	{
		$this->settings = $settings;
		return $this;
	}
}
