<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use Nette\Security\Resource;
use Nette\Security\Role;

interface AclRole extends Role, Entity
{
	// AclRole interface (Nette)
	public function getRoleId(): string;

	// Identifikátor
	public function getId(): ?int;

	// Název (přeložený)
	public function getName(): string;
	public function setName(string $name): static;

	// Přístupová práva
	public function isAllowed(Resource $aclResource): bool;

	// Kontext
	public function getContext(): ?string;
	public function setContext(?string $context): static;

	// AclRole flags
	public function getIsAdmin(): bool;
	public function setIsAdmin(bool $isAdmin): static;

	// SSO
	public function getNeedsSso(): bool;
	public function setNeedsSso(bool $needsSso): static;

	// Vynucené přihlášení klíčem
	public function getNeeds2fa(): bool;
	public function setNeeds2fa(bool $needs2fa): static;

	public function getPasswordPolicyEnabled(): bool;
	public function setPasswordPolicyEnabled(bool $passwordPolicyEnabled): static;

	public function getPasswordMinLength(): ?int;
	public function setPasswordMinLength(?int $passwordMinLength): static;

	public function getPasswordRequireUppercase(): bool;
	public function setPasswordRequireUppercase(bool $passwordRequireUppercase): static;

	public function getPasswordRequireLowercase(): bool;
	public function setPasswordRequireLowercase(bool $passwordRequireLowercase): static;

	public function getPasswordRequireDigit(): bool;
	public function setPasswordRequireDigit(bool $passwordRequireDigit): static;

	public function getPasswordRequireSpecialChar(): bool;
	public function setPasswordRequireSpecialChar(bool $passwordRequireSpecialChar): static;

	public function getSessionExpirationMinutes(): ?int;
	public function setSessionExpirationMinutes(?int $sessionExpirationMinutes): static;

	// Zdroje
	/**
	 * @return AclResource[]
	 */
	public function getResources(): array;
}
