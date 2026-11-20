<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Tablet\TabletSignDocument;
use ADT\Files\Entities\File;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce podepsaného dokumentu (rozhraní SignedDocument): čeho se podpis týká, otisk
 * originálu a podepsané PDF. Projektová entita: #[ORM\Entity] #[ORM\Table(name: 'signed_document')]
 * + implements SignedDocument + use SignedDocumentTrait, konstruktor bez povinných parametrů.
 * Entity TabletSignRequest a File projektu musí být ve stejném jmenném prostoru.
 */
trait SignedDocumentTrait
{
	use CreatedAt;

	/** Žádost, kterou byl dokument poslán na tablet - evidence zůstává i bez ní */
	#[ORM\ManyToOne(targetEntity: 'TabletSignRequest')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?TabletSignRequest $request = null;

	/** Klíč dokumentu v žádosti - podpisy (DocumentSignature::getPlacements()) se na něj odkazují */
	#[ORM\Column(length: 64)]
	protected string $documentKey;

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

	/** SHA-256 PDF, které podepisující viděl */
	#[ORM\Column(length: 64, nullable: true)]
	protected ?string $originalPdfHash = null;

	/** Podepsané PDF */
	#[ORM\ManyToOne(targetEntity: 'File')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?File $file = null;

	/** SHA-256 podepsaného PDF */
	#[ORM\Column(length: 64, nullable: true)]
	protected ?string $pdfHash = null;

	public static function createForRequest(TabletSignRequest $request, string $key, TabletSignDocument $document, \DateTimeImmutable $now): static
	{
		$signed = new static();
		$signed->request = $request;
		$signed->documentKey = $key;
		$signed->title = $document->title;
		$signed->type = $document->type ?? $request->getType();
		$signed->subjectId = $document->subjectId ?? $request->getSubjectId();
		$signed->data = $document->data ?? $request->getData();
		$signed->setCreatedAt($now);

		return $signed;
	}

	public function getRequest(): ?TabletSignRequest
	{
		return $this->request;
	}

	public function getDocumentKey(): string
	{
		return $this->documentKey;
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

	public function getOriginalPdfHash(): ?string
	{
		return $this->originalPdfHash;
	}

	public function recordOriginalPdf(string $pdfContent): static
	{
		$this->originalPdfHash = hash('sha256', $pdfContent);

		return $this;
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

	public function getPdfHash(): ?string
	{
		return $this->pdfHash;
	}

	public function recordPdf(string $pdfContent): static
	{
		$this->pdfHash = hash('sha256', $pdfContent);

		return $this;
	}
}
