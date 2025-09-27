<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use App\Model\Entities\Company;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Nette\Security\Passwords;

trait IdentityTrait
{
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;
	use IsActive;

	abstract protected function getProfile();

	#[ORM\Column(nullable:false)]
	protected string $firstName;

	#[ORM\Column(nullable:false)]
	protected string $lastName;

	#[ORM\Column(unique: true, nullable:true)]
	protected ?string $email = null;

	#[ORM\Column(nullable:true)]
	protected ?string $username = null;

	#[ORM\Column(unique: true, nullable:true)]
	protected ?string $phoneNumber = null;

	#[ORM\Column(nullable: true)]
	protected ?string $password = null;

	#[ORM\OneToMany(targetEntity: 'Profile', mappedBy: 'identity', cascade: ["persist", "remove"], orphanRemoval: true)]
	protected Collection $profiles;

	protected string $authToken;
	public ?string $context = null;

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

	public function getFirstName(): string
	{
		return $this->firstName;
	}

	public function setFirstName(?string $firstName): static
	{
		$this->firstName = $firstName;
		return $this;
	}

	public function getLastName(): string
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

	public function getAuthToken(): ?string
	{
		return $this->authToken;
	}

	public function setAuthToken(string $token): void
	{
		$this->authToken = $token;
	}

	/**
	 * @return AclRole[]
	 */
	public function getRoles(): array
	{
		return $this->getProfile()->getRoles();
	}

	public function isAllowed(string $aclResource): bool
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

	/**
	 * @return Profile[]
	 */
	public function getAllowedProfiles(?string $context = null): array
	{
		$context = $context ?: $this->context;

		$profiles = [];
		/** @var Profile $_profile */
		foreach ($this->profiles as $_profile) {
			if (!$_profile->getIsActive()) {
				continue;
			}

			if ($context && !$_profile->isAllowedContext($context)) {
				continue;
			}

			$profiles[] = $_profile;
		}

		return $profiles;
	}

	public function setContext(string $context): void
	{
		$this->context = $context;
	}

	public function isAllowedContext(string $context): bool
	{
		return (bool) $this->getAllowedProfiles($context);
	}

	public function getGravatar(): string
	{
		return '//www.gravatar.com/avatar/' . md5($this->getEmail()) . '?s=90&d=mp';
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
		return $this->filteredCompany;
	}

	public function setSelectedAccount(?A $filteredCompany): self
	{
		$this->filteredCompany = $filteredCompany;
		return $this;
	}
}
