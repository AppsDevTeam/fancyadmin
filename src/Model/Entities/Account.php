<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface Account extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;
	public function getParent(): ?Account;
	public function setParent(?Account $parent): static;
	public function getSubaccounts(): array;
}
