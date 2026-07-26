<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use Nette\Security\Passwords;
use Nette\Security\Resource;

trait IdentityTrait
{
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;
	use IsActive;
	use \ADT\DoctrineAuthenticator\OTP\IdentityTrait;

	abstract public function getId();

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $firstName = null;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $lastName = null;

	#[ORM\Column(nullable:true)]
	#[LoggableProperty]
	protected ?string $email = null;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $username = null;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $context = null;

	#[ORM\Column(nullable:true)]
	#[LoggableProperty]
	protected ?string $phoneNumber = null;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?string $password = null;

	#[ORM\OneToMany(targetEntity: 'Profile', mappedBy: 'identity', cascade: ["persist", "remove"], orphanRemoval: true)]
	protected Collection $profiles;

	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[JoinColumn(nullable: true)]
	protected ?Account $selectedAccount = null;

	#[ORM\ManyToOne(targetEntity: 'Sso')]
	#[JoinColumn(nullable: true)]
	#[LoggableProperty]
	protected ?Sso $sso = null;

	#[ManyToMany(targetEntity: 'AclRole')]
	#[JoinColumn(onDelete: "CASCADE")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	#[LoggableProperty]
	protected Collection $roles;

	#[ORM\Column(nullable: true)]
	#[LoggableProperty]
	protected ?DateTimeImmutable $anonymizedAt = null;

	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	#[LoggableProperty]
	protected ?Identity $anonymizedBy = null;

	protected string $authToken;

	public function __construct()
	{
		$this->profiles = new ArrayCollection();
		$this->roles = new ArrayCollection();
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): static
	{
		if ($password) {
			$this->password = new Passwords()->hash($password);			
		}
		return $this;
	}

	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	public function setFirstName(?string $firstName): static
	{
		$this->firstName = $firstName;
		return $this;
	}

	public function getLastName(): ?string
	{
		return $this->lastName;
	}

	public function setLastName(?string $lastName): static
	{
		$this->lastName = $lastName;
		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): static
	{
		$this->email = $email;
		return $this;
	}

	public function getPhoneNumber(): ?string
	{
		return $this->phoneNumber;
	}

	public function setPhoneNumber(?string $phoneNumber): static
	{
		$this->phoneNumber = $phoneNumber;
		return $this;
	}

	public function getFullName(): string
	{
		$fullName = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
		return $fullName !== '' ? $fullName : ($this->username ?? '');
	}

	public function getAuthObjectId(): string
	{
		return (string) $this->getId();
	}

	public function getAuthToken(): string
	{
		return $this->authToken;
	}

	public function setAuthToken(string $token): void
	{
		$this->authToken = $token;
	}

	public function isAllowed(string|Resource $aclResource): bool
	{
		return array_any($this->getRoles(), fn(AclRole $_role) => $_role->getIsAdmin() || $_role->isAllowed($aclResource));
	}

	public function isAdmin(): bool
	{
		return array_any($this->getRoles(), fn(AclRole $role) => $role->getIsAdmin());
	}

	public function getAuthMetadata(): array
	{
		return [];
	}

	public function setAuthMetadata(array $metadata): void
	{
	}

	/**
	 * @return Profile[]
	 */
	public function getProfiles(): array
	{
		return $this->profiles->toArray();
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

	public function addProfile(Profile $profile): static
	{
		if ($this->profiles->contains($profile)) {
			return $this;
		}
		$this->profiles->add($profile);
		$profile->setIdentity($this);
		return $this;
	}

	public function getUsername(): ?string
	{
		return $this->username;
	}

	public function setUsername(?string $username): static
	{
		$this->username = $username;
		return $this;
	}

	public function getSelectedAccount(): ?Account
	{
		return $this->selectedAccount;
	}

	public function setSelectedAccount(?Account $selectedAccount): static
	{
		$this->selectedAccount = $selectedAccount;
		return $this;
	}

	public function getSso(): ?Sso
	{
		return $this->sso;
	}

	public function setSso(?Sso $sso): static
	{
		$this->sso = $sso;
		return $this;
	}

	public function getProfile(): ?Profile
	{
		$selectedAccount = $this->getSelectedAccount();
		foreach ($this->getProfiles() as $_profile) {
			if ($_profile->getAccount() === $selectedAccount) {
				return $_profile;
			}
		}
		if ($selectedAccount?->getParent()) {
			foreach ($this->getProfiles() as $_profile) {
				if ($_profile->getAccount() === $selectedAccount->getParent()) {
					return $_profile;
				}
			}
		}
		return null;
	}

	public function getGravatar(string $d = 'mp'): string
	{
		return 'https://www.gravatar.com/avatar/' . hash("sha256", strtolower(trim($this->getEmail()))) . '?s=90&d=' . urlencode($d);
	}

	/**
	 * @return Account[]
	 */
	public function getAccounts(): array
	{
		$accounts = [];
		foreach ($this->getProfiles() as $_profile) {
			$accounts[] = $_profile->getAccount();
		}
		return $accounts;
	}

	/**
	 * @return Account[]
	 */
	public function getSubaccounts(): array
	{
		$subaccounts = [];
		foreach ($this->getAccounts() as $_account) {
			$subaccounts = array_merge($subaccounts, $_account->getSubaccounts());
		}
		return $subaccounts;
	}

	/**
	 * @return AclRole[]
	 */
	public function getRoles(): array
	{
		return array_merge($this->roles->toArray(), $this->getProfile()?->getRoles() ?: []);
	}

	public function addRole(AclRole $role): static
	{
		if ($this->roles->contains($role)) {
			return $this;
		}
		$this->roles->add($role);
		return $this;
	}

	public function getAnonymizedAt(): ?DateTimeImmutable
	{
		return $this->anonymizedAt;
	}

	public function setAnonymizedAt(?DateTimeImmutable $anonymizedAt): static
	{
		$this->anonymizedAt = $anonymizedAt;
		return $this;
	}

	public function getAnonymizedBy(): ?Identity
	{
		return $this->anonymizedBy;
	}

	public function setAnonymizedBy(?Identity $anonymizedBy): static
	{
		$this->anonymizedBy = $anonymizedBy;
		return $this;
	}

	public function getIdentity(): Identity
	{
		return $this;
	}
}
