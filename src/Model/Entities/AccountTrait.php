<?php

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping as ORM;

trait AccountTrait
{
	use CreatedAt;
	use CreatedByNullable;
	use UpdatedAt;
	use UpdatedBy;
	#[Column(nullable: false)]
	#[LoggableProperty]
	protected string $name;

	#[ORM\OneToMany(targetEntity: 'Account', mappedBy: 'parent')]
	protected Collection $accounts;

	/**
	 * Inverzni strana k Profile::$account - ProfileTrait na ni ukazuje pres inversedBy,
	 * takze bez ni je mapovani nekonzistentni a orm:validate-schema padne.
	 */
	#[ORM\OneToMany(targetEntity: 'Profile', mappedBy: 'account')]
	protected Collection $profiles;

	// inversedBy kvuli $accounts vyse - oba konce stromu uctu musi na sebe ukazovat,
	// jinak je mapovani nekonzistentni.
	#[ORM\ManyToOne(targetEntity: 'Account', inversedBy: 'accounts')]
	#[LoggableProperty]
	#[AuditedValue]
	protected ?Account $parent = null;
	
	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getParent(): ?Account
	{
		return $this->parent;
	}

	public function setParent(?Account $parent): static
	{
		$this->parent = $parent;
		return $this;
	}

	/**
	 * @return Account[]
	 */
	public function getSubaccounts(): array
	{
		return $this->accounts->toArray();
	}

	/**
	 * @return Profile[]
	 */
	public function getProfiles(): array
	{
		return $this->profiles->toArray();
	}
}