<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;

/**
 * Ulozeny filtr datagridu.
 *
 * Unikatnost nazvu si urci entita sama, protoze se lisi podle projektu: projekt s ucty
 * ji chce v ramci uctu (grid, name, account), projekt bez nich globalne (grid, name).
 *
 * Ucet je nullable zamerne: v backoffice se identite vybrany ucet nuluje
 * ({@see \ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait::startup()}), takze filtr
 * ulozeny nad backoffice gridem k zadnemu uctu nepatri a je spolecny pro vsechny
 * backoffice uzivatele.
 */
trait GridFilterTrait
{
	use CreatedByNullable;
	use CreatedAt;

	#[ORM\Column(nullable: false)]
	protected string $grid;

	#[ORM\Column(nullable: false)]
	protected string $name;

	#[Column(type: "json", nullable: false)]
	protected array $value = [];

	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[JoinColumn(nullable: true)]
	protected ?Account $account = null;

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): static
	{
		$this->grid = $grid;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getValue(): array
	{
		return $this->value;
	}

	public function setValue(array $value): static
	{
		$this->value = $value;
		return $this;
	}

	public function getAccount(): ?Account
	{
		return $this->account;
	}

	public function setAccount(?Account $account): self
	{
		$this->account = $account;
		return $this;
	}
}
