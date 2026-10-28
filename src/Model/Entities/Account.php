<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineLoggable\Attributes\LoggableEntity;

// založení se loguje kvůli auditní stopě - viz ChangeLogAuditSubscriber
#[LoggableEntity(logCreate: true)]
interface Account extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;
	public function getParent(): ?Account;
	public function setParent(?Account $parent): static;
	public function getSubaccounts(): array;
}
