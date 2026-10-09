<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use Nette\Security\Resource;
use SensitiveParameter;

/**
 * Prihlaseny uzivatel s predem danymi opravnenimi - misto ACL se rozhoduje podle seznamu
 * povolenych zdroju, takze test popisuje, co uzivatel smi, jednim polem.
 */
final class TestSecurityUser implements SecurityUser
{
	/** @var list<string> */
	public array $askedFor = [];

	/** @param list<string> $allowedResources */
	public function __construct(
		private array $allowedResources = [],
		private readonly bool $isAdmin = false,
		private readonly bool $isLoggedIn = true,
		private readonly ?Identity $identity = null,
	) {
	}

	public function getId(): ?int
	{
		return $this->identity?->getId();
	}

	public function getIdentity(): ?IIdentity
	{
		return $this->identity;
	}

	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool
	{
		$resourceId = $resource instanceof Resource ? $resource->getResourceId() : (string) $resource;
		$this->askedFor[] = $resourceId;

		return $this->isAdmin || in_array($resourceId, $this->allowedResources, true);
	}

	public function isAllowedFullDataAclResource(): bool
	{
		return $this->isAllowed('fullData');
	}

	public function isAllowedBackoffice(): bool
	{
		return $this->isAllowed('portalBackoffice.dashboard');
	}

	public function isLoggedIn(): bool
	{
		return $this->isLoggedIn;
	}

	public function isAdmin(): bool
	{
		return $this->isAdmin;
	}

	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = [],
	): void
	{
	}
}
