<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface Sso extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;
}
