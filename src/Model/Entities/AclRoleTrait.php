<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Security\Resource;

trait AclRoleTrait
{
	use CreatedAt;
	use CreatedByNullable;
	use UpdatedAt;
	use UpdatedBy;

	#[ORM\Column(unique: true, nullable: false)]
	#[LoggableProperty]
	protected string $name;

	#[ORM\OneToMany(targetEntity: 'Acl', mappedBy: 'role')]
	#[LoggableProperty]
	protected Collection $acls;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $context = null;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected AclRoleTypeEnum $type;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $isAdmin = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $needsSso = false;

	// Vynucené přihlášení klíčem: heslo takové identitě nefunguje. SSO má přednost
	// a při vypnutém passkeyEnabled je flag inertní (viz README 19.8).
	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $needs2fa = false;

	// Politika hesel role. Identita jich muze mit vic, takze se pri nastavovani hesla
	// uplatni nejprisnejsi z nich - viz Model\Security\PasswordPolicy::strictestOf().
	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $passwordPolicyEnabled = false;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?int $passwordMinLength = null;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $passwordRequireUppercase = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $passwordRequireLowercase = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $passwordRequireDigit = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	#[LoggableProperty]
	protected bool $passwordRequireSpecialChar = false;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?int $sessionExpirationMinutes = null;

	public function __construct()
	{
		$this->acls = new ArrayCollection();
	}

	public function getRoleId(): string
	{
		return (string) $this->getName();
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return AclResource[]
	 */
	public function getResources(): array
	{
		return array_map(
			fn(Acl $acl) => $acl->getResource(),
			array_filter($this->acls->toArray(), fn(Acl $acl) => $acl->getIsActive()),
		);
	}

	public function getIsAdmin(): bool
	{
		return $this->isAdmin;
	}

	public function setIsAdmin(bool $isAdmin): static
	{
		$this->isAdmin = $isAdmin;
		return $this;
	}

	public function isAllowed(Resource $resource): bool
	{
		if ($this->isAdmin) {
			return true;
		}

		return array_any($this->getResources(), fn(AclResource $_resource) => $_resource->getName() === $resource->getResourceId());
	}

	public function getContext(): ?string
	{
		return $this->context;
	}

	public function setContext(?string $context): static
	{
		$this->context = $context;
		return $this;
	}

	public function getNeedsSso(): bool
	{
		return $this->needsSso;
	}

	public function setNeedsSso(bool $needsSso): static
	{
		$this->needsSso = $needsSso;
		return $this;
	}

	public function getNeeds2fa(): bool
	{
		return $this->needs2fa;
	}

	public function setNeeds2fa(bool $needs2fa): static
	{
		$this->needs2fa = $needs2fa;
		return $this;
	}

	public function getPasswordPolicyEnabled(): bool
	{
		return $this->passwordPolicyEnabled;
	}

	public function setPasswordPolicyEnabled(bool $passwordPolicyEnabled): static
	{
		$this->passwordPolicyEnabled = $passwordPolicyEnabled;
		return $this;
	}

	public function getPasswordMinLength(): ?int
	{
		return $this->passwordMinLength;
	}

	public function setPasswordMinLength(?int $passwordMinLength): static
	{
		$this->passwordMinLength = $passwordMinLength;
		return $this;
	}

	public function getPasswordRequireUppercase(): bool
	{
		return $this->passwordRequireUppercase;
	}

	public function setPasswordRequireUppercase(bool $passwordRequireUppercase): static
	{
		$this->passwordRequireUppercase = $passwordRequireUppercase;
		return $this;
	}

	public function getPasswordRequireLowercase(): bool
	{
		return $this->passwordRequireLowercase;
	}

	public function setPasswordRequireLowercase(bool $passwordRequireLowercase): static
	{
		$this->passwordRequireLowercase = $passwordRequireLowercase;
		return $this;
	}

	public function getPasswordRequireDigit(): bool
	{
		return $this->passwordRequireDigit;
	}

	public function setPasswordRequireDigit(bool $passwordRequireDigit): static
	{
		$this->passwordRequireDigit = $passwordRequireDigit;
		return $this;
	}

	public function getPasswordRequireSpecialChar(): bool
	{
		return $this->passwordRequireSpecialChar;
	}

	public function setPasswordRequireSpecialChar(bool $passwordRequireSpecialChar): static
	{
		$this->passwordRequireSpecialChar = $passwordRequireSpecialChar;
		return $this;
	}

	public function getSessionExpirationMinutes(): ?int
	{
		return $this->sessionExpirationMinutes;
	}

	public function setSessionExpirationMinutes(?int $sessionExpirationMinutes): static
	{
		$this->sessionExpirationMinutes = $sessionExpirationMinutes;
		return $this;
	}

	public function getType(): AclRoleTypeEnum
	{
		return $this->type;
	}

	public function setType(AclRoleTypeEnum $type): static
	{
		$this->type = $type;
		return $this;
	}
}
