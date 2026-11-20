<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\BiometricSignature\Entity\BiometricSignatureTrait;
use ADT\BiometricSignature\SignatureCapture;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce podpisu na tabletu (rozhraní DocumentSignature): biometrický podpis
 * (BiometricSignatureTrait z adt/biometric-signature), žádost a místa, do kterých byl vložen.
 * Projektová entita: #[ORM\Entity] #[ORM\Table(name: 'document_signature')] + implements
 * DocumentSignature + use DocumentSignatureTrait, konstruktor bez povinných parametrů. Entita
 * TabletSignRequest projektu musí být ve stejném jmenném prostoru. Entitu nelogovat do change
 * logu - biometrika má stovky kB.
 */
trait DocumentSignatureTrait
{
	use CreatedAt;
	use BiometricSignatureTrait;

	/** Žádost, ke které byl podpis nakreslen - evidence zůstává i bez ní */
	#[ORM\ManyToOne(targetEntity: 'TabletSignRequest')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?TabletSignRequest $request = null;

	/** @var list<array{document: string, box: int|null}> */
	#[ORM\Column(type: 'json')]
	protected array $placements = [];

	public static function createForRequest(TabletSignRequest $request, SignatureCapture $capture, \DateTimeImmutable $now): static
	{
		$signature = new static();
		// na tabletu provozovatele se kód neposílá (totožnost ověřil personál) - bez telefonu
		$signature->initializeBiometricSignature($capture, '');
		$signature->setOnSite(true);
		$signature->request = $request;
		$signature->setCreatedAt($now);

		return $signature;
	}

	public function getRequest(): ?TabletSignRequest
	{
		return $this->request;
	}

	public function getPlacements(): array
	{
		return $this->placements;
	}

	public function setPlacements(array $placements): static
	{
		$this->placements = $placements;

		return $this;
	}
}
