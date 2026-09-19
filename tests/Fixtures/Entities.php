<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineAuthenticator\OTP\Identity as OtpIdentity;
use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Entities\Acl;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclResourceTrait;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\AclRoleTrait;
use ADT\FancyAdmin\Model\Entities\AclTrait;
use ADT\FancyAdmin\Model\Entities\AccountTrait;
use ADT\FancyAdmin\Model\Entities\ApiKey;
use ADT\FancyAdmin\Model\Entities\ApiKeyTrait;
use ADT\FancyAdmin\Model\Entities\AuditLog;
use ADT\FancyAdmin\Model\Entities\AuditLogTrait;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Entities\ConfigurationTrait;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Entities\GridFilter;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\IdentityPasskeysTrait;
use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Entities\PasskeyTrait;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Entities\ProfileTrait;
use ADT\FancyAdmin\Model\Entities\RequestLog;
use ADT\FancyAdmin\Model\Entities\RequestLogBody;
use ADT\FancyAdmin\Model\Entities\RequestLogBodyTrait;
use ADT\FancyAdmin\Model\Entities\RequestLogTrait;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\Entities\SsoTrait;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Entity, ktere by si jinak vytvoril projekt - balicek dodava jen traity a rozhrani.
 * Slouzi k tomu, aby sly traity otestovat presne tak, jak se pouzivaji.
 */
final class TestAccount implements Account
{
	use Identifier;
	use AccountTrait;

	public function __construct(string $name = 'Account', ?Account $parent = null)
	{
		$this->accounts = new ArrayCollection();
		$this->name = $name;
		$this->parent = $parent;
	}

	public function addSubaccount(Account $account): static
	{
		$this->accounts->add($account);
		$account->setParent($this);
		return $this;
	}

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}
}

final class TestAclResource implements AclResource
{
	use Identifier;
	use AclResourceTrait;

	public function __construct(string $name = 'resource', ?string $title = null)
	{
		$this->name = $name;
		$this->title = $title ?? $name;
	}

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}
}

final class TestAcl implements Acl
{
	use Identifier;
	use AclTrait;

	public function __construct(AclRole $role, AclResource $resource, bool $isActive = true)
	{
		$this->role = $role;
		$this->resource = $resource;
		$this->isActive = $isActive;
	}
}

final class TestAclRole implements AclRole
{
	use Identifier;
	use AclRoleTrait {
		AclRoleTrait::__construct as private initAclRoleTrait;
	}

	public function __construct(string $name = 'role', bool $isAdmin = false, AclRoleTypeEnum $type = AclRoleTypeEnum::IDENTITY)
	{
		$this->initAclRoleTrait();
		$this->name = $name;
		$this->isAdmin = $isAdmin;
		$this->type = $type;
	}

	/** Acl je vlastnena roli, ale v balicku neni setter - v projektu ji doplni Doctrine. */
	public function addAcl(Acl $acl): static
	{
		$this->acls->add($acl);
		return $this;
	}

	public function allowResource(AclResource $resource, bool $isActive = true): static
	{
		return $this->addAcl(new TestAcl($this, $resource, $isActive));
	}

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}
}

final class TestIdentity implements Identity, OtpIdentity, HasPasskeys
{
	use Identifier;
	use IdentityTrait {
		IdentityTrait::__construct as private initIdentityTrait;
	}
	use IdentityPasskeysTrait;

	public function __construct()
	{
		$this->initIdentityTrait();
		$this->passkeys = new ArrayCollection();
	}

	public function addPasskey(Passkey $passkey): static
	{
		$this->passkeys->add($passkey);
		return $this;
	}

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}

	/** Obejde hashovani v setPassword() - pro testy, ktere overuji ulozenou hodnotu. */
	public function setRawPassword(?string $password): static
	{
		$this->password = $password;
		return $this;
	}
}

/** Identita bez passkey infrastruktury - projekt ji tak muze mit, dokud passkeys nezapne. */
final class TestIdentityWithoutPasskeys implements Identity, OtpIdentity
{
	use Identifier;
	use IdentityTrait;

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}
}

final class TestProfile implements Profile
{
	use Identifier;
	use ProfileTrait {
		ProfileTrait::__construct as private initProfileTrait;
	}

	public function __construct(?Identity $identity = null, ?Account $account = null)
	{
		$this->initProfileTrait();

		if ($account !== null) {
			$this->setAccount($account);
		}
		if ($identity !== null) {
			$this->setIdentity($identity);
		}
	}

	public function setId(?int $id): static
	{
		$this->id = $id;
		return $this;
	}
}

final class TestApiKey implements ApiKey
{
	use Identifier;
	use ApiKeyTrait;

	public function __construct(string $name = 'key')
	{
		$this->name = $name;
	}
}

final class TestConfiguration implements Configuration
{
	use Identifier;
	use ConfigurationTrait;

	public function __construct(string $key = 'key', string $name = 'name')
	{
		$this->key = $key;
		$this->name = $name;
		$this->file = null;
	}
}

final class TestPasskey implements Passkey
{
	use Identifier;
	use PasskeyTrait;

	public function __construct(Identity $identity, string $name = 'klic', string $credentialId = 'cred')
	{
		$this->identity = $identity;
		$this->name = $name;
		$this->credentialId = $credentialId;
		$this->publicKey = '-----BEGIN PUBLIC KEY-----';
		$this->createdAt = new DateTimeImmutable('2026-01-01 00:00:00');
	}

	/** DBAL u binary sloupcu podle verze vraci stream misto stringu - getter to musi ustat. */
	public function setRawCredentialId(mixed $value): static
	{
		$this->credentialId = $value;
		return $this;
	}

	public function setRawAaguid(mixed $value): static
	{
		$this->aaguid = $value;
		return $this;
	}
}

final class TestSso implements Sso
{
	use Identifier;
	use SsoTrait;

	public function __construct(
		string $name = 'default',
		string $hostUrl = 'https://auth.example.com',
		bool $isActive = true,
	) {
		$this->name = $name;
		$this->realm = 'test-realm';
		$this->baseUrl = 'https://internal.example.com';
		$this->hostUrl = $hostUrl;
		$this->clientId = 'client';
		$this->clientSecret = 'secret';
		$this->frontendClientId = 'frontend';
		$this->isActive = $isActive;
	}
}

final class TestAuditLog implements AuditLog
{
	use Identifier;
	use AuditLogTrait;

	public function __construct(
		string $action = 'test.action',
		string $outcome = 'success',
		?DateTimeImmutable $createdAt = null,
	) {
		$this->action = $action;
		$this->outcome = $outcome;
		$this->createdAt = $createdAt ?? new DateTimeImmutable('2026-03-01 12:00:00', new DateTimeZone('UTC'));
	}

	public function fill(
		?string $createdById = null,
		?string $createdByLabel = null,
		?array $createdBy = null,
		?string $sourceIp = null,
		?string $userAgent = null,
		?string $correlationId = null,
		?array $payload = null,
	): static
	{
		$this->createdById = $createdById;
		$this->createdByLabel = $createdByLabel;
		$this->createdBy = $createdBy;
		$this->sourceIp = $sourceIp;
		$this->userAgent = $userAgent;
		$this->correlationId = $correlationId;
		$this->payload = $payload;
		return $this;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;
		return $this;
	}
}

final class TestRequestLog implements RequestLog
{
	use Identifier;
	use RequestLogTrait;
}

final class TestRequestLogBody implements RequestLogBody
{
	use Identifier;
	use RequestLogBodyTrait;
}

final class TestGridFilter
{
	use Identifier;
	use GridFilter;
}

/**
 * Identita s passkeys, ktera si kolekci klicu neinicializuje v konstruktoru -
 * presne jako cerstve vytvorena entita v projektu.
 */
final class TestLazyPasskeyIdentity implements Identity, HasPasskeys
{
	use Identifier;
	use IdentityTrait;
	use IdentityPasskeysTrait;
}
