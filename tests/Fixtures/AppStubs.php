<?php

declare(strict_types=1);

/**
 * Tridy, ktere balicek ocekava od projektu.
 *
 * ADT\FancyAdmin\Model\Listeners\CreatedByListenerTrait a Entities\Traits\CreatedBy
 * se odkazuji na App\Model\Security\SecurityUser, resp. App\Model\Entities\Identity.
 * V balicku zadna takova trida neni, takze bez teto nahrady je nejde ani nacist -
 * fixtures proto dodavaji minimalni variantu toho, co si projekt vytvari sam.
 */

namespace App\Model\Security {

	interface SecurityUser extends \ADT\FancyAdmin\Model\Security\SecurityUser
	{
	}
}

namespace App\Model\Entities {

	interface Identity extends \ADT\FancyAdmin\Model\Entities\Identity
	{
	}
}

namespace ADT\FancyAdmin\Tests\Fixtures {

	use ADT\DoctrineComponents\Entities\Traits\Identifier;
	use ADT\FancyAdmin\Model\Entities\IdentityTrait;
	use ADT\FancyAdmin\Model\Entities\Traits\CreatedBy;
	use ADT\FancyAdmin\Model\Entities\Traits\CreatedByInterface;
	use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
	use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
	use Nette\Security\Authorizator;
	use Nette\Security\IIdentity;
	use SensitiveParameter;

	/** Identita v projektovem namespace - jak ji ocekava trait CreatedBy. */
	final class TestAppIdentity implements \App\Model\Entities\Identity
	{
		use Identifier;
		use IdentityTrait;

		public function setId(?int $id): static
		{
			$this->id = $id;
			return $this;
		}
	}

	/** Entita s povinnym autorem - trait CreatedBy, ne CreatedByNullable. */
	final class TestCreatedByEntity implements CreatedByInterface, UpdatedByInterface
	{
		use Identifier;
		use CreatedBy;
		use UpdatedBy;
	}

	final class TestAppSecurityUser implements \App\Model\Security\SecurityUser
	{
		public function __construct(
			private readonly ?IIdentity $identity = null,
			private readonly bool $isLoggedIn = true,
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
			return true;
		}

		public function isAllowedFullDataAclResource(): bool
		{
			return true;
		}

		public function isAllowedBackoffice(): bool
		{
			return true;
		}

		public function isLoggedIn(): bool
		{
			return $this->isLoggedIn;
		}

		public function isAdmin(): bool
		{
			return true;
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
}
