<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\Pdf\SignatureBox;

/**
 * Místo, kam se člověk u tabletu podepisuje (TabletSigning::getPlaces()): označené místo v PDF
 * dokumentu (SignatureBox), nebo - u dokumentu bez označených míst - jedno místo pod náhledem.
 */
final class TabletSignPlace
{
	public function __construct(
		/** Klíč dokumentu v žádosti */
		public readonly string $document,
		/** Pořadí místa v PDF (SignatureStamper::findBoxes(), od 0), null = místo pod náhledem */
		public readonly ?int $box = null,
		/** Označené místo v PDF: název, stránka, poloha a rozměr v bodech */
		public readonly ?SignatureBox $signatureBox = null,
	) {
	}

	/** Identifikace místa ve formuláři stránky podpisu - dokument a pořadí místa */
	public function getId(): string
	{
		return $this->document . ':' . ($this->box ?? '-');
	}

	/** @return array{id: string, document: string, box: int|null, name: string|null, page: int|null, x: float|null, y: float|null, width: float|null, height: float|null} */
	public function toArray(): array
	{
		return [
			'id' => $this->getId(),
			'document' => $this->document,
			'box' => $this->box,
			'name' => $this->signatureBox?->name,
			'page' => $this->signatureBox?->page,
			'x' => $this->signatureBox?->x,
			'y' => $this->signatureBox?->y,
			'width' => $this->signatureBox?->width,
			'height' => $this->signatureBox?->height,
		];
	}
}
