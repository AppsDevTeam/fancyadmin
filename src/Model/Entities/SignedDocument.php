<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Tablet\TabletSignDocument;
use ADT\Files\Entities\File;
use DateTimeImmutable;

/**
 * Podepsaný dokument - jeden řádek za každý dokument podepsaný na tabletu (docs/tablets.md,
 * Podpis dokumentu): čeho se podpis týká (druh dokumentu, ID záznamu, data - z dokumentu nebo
 * žádosti), otisk PDF, které podepisující viděl, a podepsané PDF (soubor adt/files). Samotné
 * podpisy s biometrikou jsou v DocumentSignature - jeden podpis může být vložený do více míst
 * i dokumentů. Entity projektu se na podepsaný dokument jen odkazují.
 *
 * Sloupce a metody dává SignedDocumentTrait, podpis na tabletu TabletSigning.
 */
interface SignedDocument extends Entity
{
	/** Podepsaný dokument k žádosti poslané na tablet - vytváří TabletSigning::sign() */
	public static function createForRequest(TabletSignRequest $request, string $key, TabletSignDocument $document, DateTimeImmutable $now): static;

	/** Žádost, kterou byl dokument poslán k podpisu na tablet (null = smazaná) */
	public function getRequest(): ?TabletSignRequest;

	/** Klíč dokumentu v žádosti (TabletSignDocument::$key, jinak pořadí) */
	public function getDocumentKey(): string;

	public function getTitle(): string;

	/** Druh podepsaného dokumentu (kód projektu) */
	public function getType(): ?string;

	/** ID záznamu projektu, kterého se podpis týká */
	public function getSubjectId(): ?string;

	/** @return array<mixed>|null */
	public function getData(): ?array;

	/** Kdy byl dokument podepsán */
	public function getCreatedAt(): DateTimeImmutable;

	/** SHA-256 PDF, které podepisující viděl (null = dokument bez PDF) */
	public function getOriginalPdfHash(): ?string;
	public function recordOriginalPdf(string $pdfContent): static;

	/** Podepsané PDF (adt/files), null = PDF (ještě) nevzniklo */
	public function getFile(): ?File;
	public function setFile(?File $file): static;

	/** SHA-256 podepsaného PDF */
	public function getPdfHash(): ?string;
	public function recordPdf(string $pdfContent): static;
}
