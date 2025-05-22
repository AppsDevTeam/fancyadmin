<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

interface AclResourceInterface
{
	// Identifikátor
	public function getId(): ?int;

	// Název
	public function getName(): string;
	public function setName(string $name): static;

	// Titulek (volitelný)
	public function getTitle(): ?string;
	public function setTitle(?string $title): static;

	// Role management
	public function addRole(AclRoleInterface $role): static;
	public function removeRole(AclRoleInterface $role): static;
}
