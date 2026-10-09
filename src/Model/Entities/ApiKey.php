<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface ApiKey extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;

	public function getKey(): ?string;
	public function setKey(?string $key): static;

	public function getAccount(): ?Account;
	public function setAccount(?Account $account): static;
}
