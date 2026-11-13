<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

/**
 * Co tablet ukáže k žádosti o podpis (TabletSignHandler::getDocument()): název, náhled
 * dokumentu (PDF se vykreslí po stránkách pod sebou) a případně text nad podpisem.
 */
final class TabletSignDocument
{
	/** @var (\Closure(): string)|null */
	private readonly ?\Closure $pdf;

	/**
	 * @param (callable(): string)|null $pdf obsah PDF k náhledu - načte se až na vyžádání stránky,
	 *        null = dokument bez PDF (podepisuje se jen text nebo samotný podpis)
	 */
	public function __construct(
		public readonly string $title,
		?callable $pdf = null,
		/** Text zobrazený nad podpisem (prostý text, řádky se zachovají) */
		public readonly ?string $text = null,
		/**
		 * Proč dokument nejde podepsat (např. už je podepsaný jinde) - stránka ukáže hlášku
		 * a návrat na čekací stránku místo podpisu, ať obsluha u tabletu vidí důvod
		 */
		public readonly ?string $error = null,
	) {
		$this->pdf = $pdf === null ? null : $pdf(...);
	}

	public function hasPdf(): bool
	{
		return $this->pdf !== null;
	}

	public function getPdf(): ?string
	{
		return $this->pdf === null ? null : ($this->pdf)();
	}
}
