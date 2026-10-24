<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineLoggable\Attributes\LoggableEntity;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationType;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;

// založení se loguje kvůli auditní stopě - viz ChangeLogAuditSubscriber
#[LoggableEntity(logCreate: true)]
interface Configuration extends Entity
{
	public function getKey(): string;
	public function setKey(string $key): static;
	public function getType(): ConfigurationTypeEnum;
	public function setType(ConfigurationTypeEnum $type): static;
	public function getValue(): ?string;
	public function setValue(?string $value): static;
	public function getFile(): ?File;
	public function setFile(?File $file): static;
}
