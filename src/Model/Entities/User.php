<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedByNullInterface;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedBy;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;

#[ORM\Entity]
class User extends BaseEntity implements DoctrineAuthenticatorIdentity, CreatedByNullInterface
{
	use Identifier;
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;

	const string SETTINGS_DATE_RANGE_FILTER_KEY = 'dateRangeFilter';
	const string SETTINGS_DASHBOARD_ADVANCED_FILTER_KEY = 'dashboardAdvancedFilter';
	const string SETTINGS_DASHBOARD_ADVANCED_TEMPORARY_FILTER_KEY = 'dashboardAdvancedTemporaryFilter';

	#[ORM\Column(nullable:false)]
	protected string $firstName;

	#[ORM\Column(nullable:false)]
	protected string $lastName;

	#[ORM\Column(unique: true, nullable:false)]
	protected string $email;

	#[ORM\Column(unique: true, nullable:true)]
	protected ?string $phoneNumber;

	#[ORM\Column(nullable: true)]
	protected ?string $password = null;

	#[ORM\Column(nullable: false, options: ["default" => 1])]
	protected bool $isActive = true;

	protected string $authToken;

	protected ?Device $device = null;

	#[ORM\OneToMany(targetEntity: UserFavourite::class, mappedBy: 'user')]
	protected Collection $favourites;

	#[ManyToMany(targetEntity: AclRole::class, inversedBy: 'users', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $roles;

	#[ORM\ManyToOne(targetEntity: Company::class)]
	#[JoinColumn(nullable: true)]
	protected ?Company $filteredCompany;

	#[ORM\OneToMany(targetEntity: UserCompany::class, mappedBy: 'user', cascade: ["persist"])]
	protected Collection $userCompanies;

	#[ORM\Column(nullable:false)]
	protected bool $isCustomer = false;

	#[Column(type: Types::JSON, nullable: true)]
	protected ?array $firebaseToken = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	protected ?array $settings = null;

	#[ORM\ManyToMany(targetEntity: Branch::class, inversedBy: 'roles', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $branches;

	public function __construct()
	{
		$this->favourites = new ArrayCollection();
		$this->roles = new ArrayCollection();
		$this->userCompanies = new ArrayCollection();
		$this->branches = new ArrayCollection();
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): self
	{
		$this->password = $password;
		return $this;
	}

	public function getFirstName(): string
	{
		return $this->firstName;
	}

	public function setFirstName(?string $firstName): self
	{
		$this->firstName = $firstName;
		return $this;
	}

	public function getLastName(): string
	{
		return $this->lastName;
	}

	public function setLastName(?string $lastName): self
	{
		$this->lastName = $lastName;
		return $this;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		return $this;
	}

	public function getPhoneNumber(): ?string
	{
		return $this->phoneNumber;
	}

	public function setPhoneNumber(?string $phoneNumber): self
	{
		$this->phoneNumber = $phoneNumber;
		return $this;
	}

	public function getIsActive(): bool
	{
		return $this->isActive;
	}

	public function setIsActive(bool $isActive): self
	{
		$this->isActive = $isActive;
		return $this;
	}

	public function getFullName(): string
	{
		return $this->firstName . " " . $this->lastName;
	}

	public function getDevice(): ?Device
	{
		return $this->device;
	}

	public function setDevice(?Device $device): User
	{
		$this->device = $device;
		return $this;
	}

	public function getAuthObjectId(): string
	{
		return (string) $this->getId();
	}

	public function getAuthMetadata(): array
	{
		if (!$this->device) {
			return [];
		}

		return [
			'device' => $this->device->getId()
		];
	}

	public function setAuthMetadata(array $metadata): void
	{
		if (isset($metadata['device'])) {
			$this->device = $metadata['device'];
		}
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
	 * @return UserFavourite[]
	 */
	public function getFavourites(): array
	{
		return $this->favourites->toArray();
	}

	public function addRole(AclRole $role): static
	{
		if ($this->roles->contains($role)) {
			return $this;
		}
		$this->roles->add($role);
		return $this;
	}

	/**
	 * @return AclRole[]
	 */
	public function getRoles(): array
	{
		//ziskame primo nastavene role uzivatele
		$roles = $this->roles->toArray();
		//budeme jeste pridavat role od jendotlivych vazeb na company
		foreach ($this->getUserCompanies() as $userCompany) {
			$roles[] = $userCompany->getRole();
		}
		return $roles;
	}

	/**
	 * @return Company[]
	 */
	public function getCompanies(): array
	{
		$companies = [];
		foreach ($this->getUserCompanies() as $userCompany) {
			$companies[] = $userCompany->getCompany();
		}

		return $companies;
	}

	public function getFilteredCompany(): ?Company
	{
		return $this->filteredCompany;
	}

	public function setFilteredCompany(?Company $filteredCompany): self
	{
		$this->filteredCompany = $filteredCompany;
		return $this;
	}

	/**
	 * @return UserCompany
	 */
	public function getUserCompanies(): array
	{
		return $this->userCompanies->toArray();
	}

	public function getIsCustomer(): bool
	{
		return $this->isCustomer;
	}

	public function setIsCustomer(bool $isCustomer): self
	{
		$this->isCustomer = $isCustomer;
		return $this;
	}

	public function getFirebaseTokens(): array
	{
		return $this->firebaseToken ?? [];
	}

	public function setFirebaseTokens(array $tokens): self
	{
		$this->firebaseToken = $tokens;
		return $this;
	}

	public function addFirebaseToken(string $firebaseToken): self
	{
		$tokens = $this->getFirebaseTokens();
		if (!in_array($firebaseToken, $tokens)) {
			$tokens[] = $firebaseToken;
		}
		return $this->setFirebaseTokens($tokens);
	}

	public function removeFirebaseToken(string $firebaseToken): self
	{
		$tokens = $this->getFirebaseTokens();

		return $this->setFirebaseTokens(
			array_filter($tokens, fn ($token) => $token !== $firebaseToken)
		);
	}

	public function getSettings(): ?array
	{
		return $this->settings;
	}

	public function setSettings(?array $settings): static
	{
		$this->settings = $settings;
		return $this;
	}

	public function getBranches(): Collection
	{
		return $this->branches;
	}

	public function addBranch(Branch $branch): static
	{
		if ($this->branches->contains($branch)) {
			return $this;
		}
		$this->branches->add($branch);
		$branch->addUser($this);
		return $this;
	}

	public function removeBranch(Branch $branch): static
	{
		if (!$this->branches->contains($branch)) {
			return $this;
		}
		$this->branches->removeElement($branch);
		$branch->removeUser($this);
		return $this;
	}

	public function isLimitedToBranch(): bool
	{
		return $this->getRoles()[0]->getIsLimitedToBranch();
	}

	public function isAllowed(string $aclResource): bool
	{
		return array_any($this->getRoles(), fn(AclRole $_role) => $_role->getIsAdmin() || $_role->isAllowed($aclResource));
	}

	public function addUserCompany(UserCompany $userCompany): static
	{
		$this->userCompanies->add($userCompany);
		return $this;
	}

	public function getRole(): AclRole
	{
		/** @var UserCompany $userCompany */
		$userCompany = $this->userCompanies->filter(fn(UserCompany $userCompany) => $userCompany->getCompany() === $this->securityUser->getIdentity()->getFilteredCompany())->first();

		return $userCompany->getRole();
	}

	public function getGravatar()
	{
		return '//www.gravatar.com/avatar/' . md5($this->getEmail()) . '?s=90&d=mp';
	}

	public function isAdmin(): bool
	{
		return array_any($this->getRoles(), fn(AclRole $role) => $role->getIsAdmin());
	}

	public function isVisitor(): bool
	{
		return array_any($this->getRoles(), fn(AclRole $role) => $role->isVisitor());
	}

	public function isCustomer(): bool
	{
		return array_any($this->getRoles(), fn(AclRole $role) => $role->getIsCustomer());
	}
}
