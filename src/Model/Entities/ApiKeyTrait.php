<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping as ORM;

trait ApiKeyTrait
{
	#[ORM\Column(nullable: false)]
	protected string $name;

	/** SHA-256 otisk klíče; samotný klíč se v čitelné podobě nikam neukládá. */
	#[ORM\Column(name: '`key`', unique: true, nullable: true)]
	protected ?string $key = null;

	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[ORM\JoinColumn(nullable: true)]
	protected ?Account $account = null;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getKey(): ?string
	{
		return $this->key;
	}

	public function setKey(?string $key): static
	{
		$this->key = $key;
		return $this;
	}

	public function getAccount(): ?Account
	{
		return $this->account;
	}

	public function setAccount(?Account $account): static
	{
		$this->account = $account;
		return $this;
	}
}
