<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\BiometricSignature\Entity\BiometricSignatureInterface;
use ADT\BiometricSignature\SignatureCapture;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\Files\Entities\File;
use DateTimeImmutable;

/**
 * Podepsaný dokument - jeden řádek za každý podpis (docs/tablets.md, Podpis dokumentu).
 * Nese biometrický podpis (obrázek, záznam průběhu psaní, důkazní záznam a jeho otisk -
 * adt/biometric-signature), podepsané PDF (soubor adt/files) a obecně to, čeho se podpis týká
 * (druh dokumentu, ID záznamu, data - převzaté ze žádosti). Entity projektu se na podepsaný
 * dokument jen odkazují.
 *
 * Sloupce a metody dává SignedDocumentTrait, podpis na tabletu TabletSigning.
 */
interface SignedDocument extends Entity, BiometricSignatureInterface
{
	/** Podpis k žádosti poslané na tablet - vytváří TabletSigning::sign() */
	public static function createForRequest(TabletSignRequest $request, SignatureCapture $capture, DateTimeImmutable $now): static;

	/** Žádost, kterou byl dokument poslán k podpisu na tablet (null = smazaná nebo podpis mimo tablet) */
	public function getRequest(): ?TabletSignRequest;

	public function getTitle(): string;

	/** Druh podepsaného dokumentu (kód projektu) */
	public function getType(): ?string;

	/** ID záznamu projektu, kterého se podpis týká */
	public function getSubjectId(): ?string;

	/** @return array<mixed>|null */
	public function getData(): ?array;

	/** Podepsané PDF (adt/files), null = PDF (ještě) nevzniklo */
	public function getFile(): ?File;
	public function setFile(?File $file): static;

	public function setOnSite(bool $onSite): static;
	public function setRequestContext(?string $ipAddress, ?string $userAgent): static;
}
