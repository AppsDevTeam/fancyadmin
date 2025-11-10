<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Nette\Security\Passwords;
use Nette\Security\Resource;

trait IdentityTrait
{
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;
	use IsActive;

	abstract public function getId();

	#[ORM\Column(nullable: true)]
	protected ?string $firstName = null;

	#[ORM\Column(nullable: true)]
	protected ?string $lastName = null;

	#[ORM\Column(nullable:true)]
	protected ?string $email = null;

	#[ORM\Column(nullable: true)]
	protected ?string $username = null;

	#[ORM\Column(nullable: true)]
	protected ?string $context = null;

	#[ORM\Column(nullable:true)]
	protected ?string $phoneNumber = null;

	#[ORM\Column(nullable: true)]
	protected ?string $password = null;

	#[ORM\OneToMany(targetEntity: 'Profile', mappedBy: 'identity', cascade: ["persist", "remove"], orphanRemoval: true)]
	protected Collection $profiles;

	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[JoinColumn(nullable: true)]
	protected ?Account $selectedAccount = null;

	#[ManyToMany(targetEntity: 'AclRole')]
	#[JoinColumn(onDelete: "CASCADE")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $roles;

	protected string $authToken;
	protected ?OnetimeToken $onetimeToken = null;

	public function __construct()
	{
		$this->profiles = new ArrayCollection();
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
		$this->email = $email ?: null;
		if ($email) {
			$this->username = $email;
		}
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
		return $this->firstName . " " . $this->lastName;
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
		$this->profiles->add($profile);
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

	public function getProfile(): ?Profile
	{
		return array_find($this->getProfiles(), fn($_profile) => $_profile->getAccount() === $this->getSelectedAccount());

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

	public function getOnetimeToken(): ?OnetimeToken
	{
		return $this->onetimeToken;
	}

	public function setOnetimeToken(?OnetimeToken $onetimeToken): static
	{
		$this->onetimeToken = $onetimeToken;
		return $this;
	}

	public function getIdentity(): Identity
	{
		return $this;
	}
}
