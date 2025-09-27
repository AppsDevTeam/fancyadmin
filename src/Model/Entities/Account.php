<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

interface Account
{
	public function getName(): string;
	public function setName(string $name): static;
}
