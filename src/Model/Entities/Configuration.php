<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationType;

interface Configuration extends Entity
{
	public function getKey(): string;
	public function setKey(string $key): static;
	public function getType(): string;
	public function setType(ConfigurationType $type): static;
	public function getValue(): string;
	public function setValue(string $value): static;
}
