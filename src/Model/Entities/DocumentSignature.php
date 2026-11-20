<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\BiometricSignature\Entity\BiometricSignatureInterface;
use ADT\BiometricSignature\SignatureCapture;
use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

/**
 * Biometrický podpis na tabletu - jeden řádek za každé nakreslení podpisu (docs/tablets.md,
 * Podpis dokumentu). Nese obrázek, záznam průběhu psaní, důkazní záznam a jeho otisk
 * (adt/biometric-signature) a místa, do kterých ho podepisující vložil: podpis nakreslený
 * jednou jde potvrdit do dalších míst a dokumentů, nový podpis platí jen pro místo, kde vznikl.
 *
 * Sloupce a metody dává DocumentSignatureTrait, podpis na tabletu TabletSigning.
 *
 * @phpstan-type Placement array{document: string, box: int|null}
 */
interface DocumentSignature extends Entity, BiometricSignatureInterface
{
	/** Podpis k žádosti poslané na tablet - vytváří TabletSigning::sign() */
	public static function createForRequest(TabletSignRequest $request, SignatureCapture $capture, DateTimeImmutable $now): static;

	/** Žádost, ke které byl podpis nakreslen (null = smazaná) */
	public function getRequest(): ?TabletSignRequest;

	/**
	 * Místa, do kterých podepisující podpis vložil (a každé potvrdil), v pořadí stránky:
	 * klíč dokumentu (SignedDocument::getDocumentKey()) a pořadí místa v PDF (null = místo pod
	 * náhledem dokumentu bez označených míst).
	 *
	 * @return list<Placement>
	 */
	public function getPlacements(): array;

	/** @param list<Placement> $placements */
	public function setPlacements(array $placements): static;

	public function setOnSite(bool $onSite): static;
	public function setRequestContext(?string $ipAddress, ?string $userAgent): static;
}
