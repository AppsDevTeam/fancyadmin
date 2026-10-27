<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\DoctrineAnonymization\Attributes\Description;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait IdentityPasskeysTrait
{
	#[ORM\OneToMany(targetEntity: 'Passkey', mappedBy: 'identity')]
	protected Collection $passkeys;

	// Náhodný opaque WebAuthn user handle, generovaný při registraci prvního klíče —
	// autentikátoru se nikdy neposílá interní ID identity
	#[ORM\Column(type: 'binary', length: 32, nullable: true, options: ['fixed' => true])]
	#[Anonymize(AnonymizationType::SECRET)]
	#[Description('WebAuthn user handle of the identity (random binary id).')]
	protected mixed $passkeyUserHandle = null;

	/**
	 * @return Passkey[]
	 */
	public function getPasskeys(): array
	{
		// konstruktor s inicializací kolekcí žije v IdentityTrait — u nové entity
		// je property neinicializovaná, ??= ji bezpečně doplní
		$this->passkeys ??= new ArrayCollection();
		return $this->passkeys->toArray();
	}

	public function getPasskeyUserHandle(): ?string
	{
		if ($this->passkeyUserHandle === null) {
			return null;
		}
		if (is_resource($this->passkeyUserHandle)) {
			rewind($this->passkeyUserHandle);
			return (string) stream_get_contents($this->passkeyUserHandle);
		}
		return (string) $this->passkeyUserHandle;
	}

	public function setPasskeyUserHandle(?string $passkeyUserHandle): static
	{
		$this->passkeyUserHandle = $passkeyUserHandle;
		return $this;
	}
}
