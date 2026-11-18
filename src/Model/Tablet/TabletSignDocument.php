<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

/**
 * Jeden dokument k podpisu na tabletu (TabletSignHandler::getDocuments()): název, náhled
 * (PDF se vykreslí po stránkách pod sebou), případně text a čeho se podpis týká.
 *
 * Kam se podepisuje, si PDF označí samo - místy pro podpis z adt/biometric-signature
 * (SignatureBox, odkaz na https://signature.invalid/<název>). Tablet je v náhledu zvýrazní
 * a podepisující se do každého podepíše zvlášť. Dokument bez označených míst (bez PDF, nebo
 * PDF bez míst) má jedno místo pod náhledem.
 */
final class TabletSignDocument
{
	/** @var (\Closure(): string)|null */
	private readonly ?\Closure $pdf;

	private ?string $pdfContent = null;

	/**
	 * @param (callable(): string)|null $pdf obsah PDF k náhledu - načte se až na vyžádání (a jen
	 *        jednou), null = dokument bez PDF (podepisuje se jen text nebo samotný podpis)
	 * @param list<string>|null $boxNames která místa pro podpis v PDF podepisuje člověk u tabletu
	 *        (název SignatureBox, např. ['client']), null = všechna; ostatní místa zůstanou
	 *        v podepsaném PDF označená k pozdějšímu podpisu
	 * @param array<mixed>|null $data
	 */
	public function __construct(
		public readonly string $title,
		?callable $pdf = null,
		/** Text zobrazený pod náhledem (prostý text, řádky se zachovají) */
		public readonly ?string $text = null,
		/**
		 * Proč dokument nejde podepsat (např. už je podepsaný jinde) - stránka ukáže hlášku
		 * a návrat na čekací stránku místo podpisu, ať obsluha u tabletu vidí důvod
		 */
		public readonly ?string $error = null,
		/** Klíč dokumentu v žádosti (v adrese náhledu a v podepsaném dokumentu), null = pořadí */
		public readonly ?string $key = null,
		/** Jméno podepisujícího - v místech pro podpis a v okně s podpisem */
		public readonly ?string $signerName = null,
		public readonly ?array $boxNames = null,
		/** Čeho se podpis týká, null = převezme se ze žádosti (TabletSignRequest::setSubject()) */
		public readonly ?string $type = null,
		public readonly ?string $subjectId = null,
		public readonly ?array $data = null,
	) {
		$this->pdf = $pdf === null ? null : $pdf(...);
	}

	/** Stejný dokument s jiným zdrojem PDF (TabletSigning ho tak bere z mezipaměti žádosti) */
	public function withPdf(callable $pdf): self
	{
		return new self(
			$this->title,
			$pdf,
			text: $this->text,
			error: $this->error,
			key: $this->key,
			signerName: $this->signerName,
			boxNames: $this->boxNames,
			type: $this->type,
			subjectId: $this->subjectId,
			data: $this->data,
		);
	}

	public function hasPdf(): bool
	{
		return $this->pdf !== null;
	}

	public function getPdf(): ?string
	{
		if ($this->pdf === null) {
			return null;
		}

		return $this->pdfContent ??= ($this->pdf)();
	}
}
