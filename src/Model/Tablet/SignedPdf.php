<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\Files\Entities\File;

/**
 * Podepsané PDF, které projekt k podpisu vygeneroval a uložil (TabletSignHandler::createSignedPdf()).
 */
final class SignedPdf
{
	public function __construct(
		/** Uložený soubor (adt/files) - naváže se na podepsaný dokument */
		public readonly File $file,
		/** Obsah PDF - jeho otisk SHA-256 se uloží k podpisu */
		public readonly string $content,
	) {
	}
}
