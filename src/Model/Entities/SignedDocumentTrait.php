<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\BiometricSignature\Entity\BiometricSignatureTrait;
use ADT\BiometricSignature\SignatureCapture;
use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\Files\Entities\File;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce podepsaného dokumentu (rozhraní SignedDocument): biometrický podpis
 * (BiometricSignatureTrait z adt/biometric-signature), čeho se podpis týká a podepsané PDF.
 * Projektová entita: #[ORM\Entity] #[ORM\Table(name: 'signed_document')] + implements
 * SignedDocument + use SignedDocumentTrait, konstruktor bez povinných parametrů. Entity
 * TabletSignRequest a File projektu musí být ve stejném jmenném prostoru. Entitu nelogovat
 * do change logu - biometrika má stovky kB.
 */
trait SignedDocumentTrait
{
	use CreatedAt;
	use BiometricSignatureTrait;

	/** Žádost, kterou byl dokument poslán na tablet - evidence zůstává i bez ní */
	#[ORM\ManyToOne(targetEntity: 'TabletSignRequest')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?TabletSignRequest $request = null;

	#[ORM\Column(length: 255)]
	protected string $title;

	/** Druh podepsaného dokumentu - kód projektu */
	#[ORM\Column(length: 100, nullable: true)]
	protected ?string $type = null;

	/** ID záznamu projektu, kterého se podpis týká */
	#[ORM\Column(length: 64, nullable: true)]
	protected ?string $subjectId = null;

	/** @var array<mixed>|null další údaje projektu */
	#[ORM\Column(type: 'json', nullable: true)]
	#[Anonymize(AnonymizationType::FREE_TEXT)]
	protected ?array $data = null;

	/** Podepsané PDF */
	#[ORM\ManyToOne(targetEntity: 'File')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?File $file = null;

	public static function createForRequest(TabletSignRequest $request, SignatureCapture $capture, \DateTimeImmutable $now): static
	{
		$document = new static();
		// na tabletu provozovatele se kód neposílá (totožnost ověřil personál) - bez telefonu
		$document->initializeBiometricSignature($capture, '');
		$document->setOnSite(true);
		$document->request = $request;
		$document->title = $request->getTitle();
		$document->type = $request->getType();
		$document->subjectId = $request->getSubjectId();
		$document->data = $request->getData();
		$document->setCreatedAt($now);

		return $document;
	}

	public function getRequest(): ?TabletSignRequest
	{
		return $this->request;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function getSubjectId(): ?string
	{
		return $this->subjectId;
	}

	public function getData(): ?array
	{
		return $this->data;
	}

	public function getFile(): ?File
	{
		return $this->file;
	}

	public function setFile(?File $file): static
	{
		$this->file = $file;

		return $this;
	}
}
