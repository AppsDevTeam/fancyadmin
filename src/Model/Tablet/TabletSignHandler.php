<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\SignedDocument;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;

/**
 * Co k podpisu dokumentu na tabletu dodává projekt (docs/tablets.md, Podpis dokumentu).
 * Stránku s náhledem a podpisem, uložení podpisu s biometrikou, důkazní záznam i evidenci dělá
 * fancyadmin (TabletSigning) - projekt jen řekne, co se podepisuje, a vyrobí podepsané PDF.
 *
 * Co se podepisuje, pozná z žádosti: getType(), getSubjectId(), getData().
 */
interface TabletSignHandler
{
	/**
	 * Co tablet k žádosti ukáže. null = žádost už nejde podepsat (záznam mezitím zanikl, je už
	 * podepsaný jinde …) - tablet se vrátí na čekací stránku a žádost se zruší.
	 */
	public function getDocument(TabletSignRequest $request): ?TabletSignDocument;

	/**
	 * Co se podepisuje - uloží se do důkazního záznamu a jeho otisk to tak pokrývá: kdo
	 * podepisuje, který dokument, otisk originálu … Jen hodnoty serializovatelné do JSON.
	 *
	 * @return array<mixed>
	 */
	public function getEvidenceSubject(SignedDocument $document): array;

	/**
	 * Podpis je ověřený, důkazní záznam a otisk jsou nastavené: vygenerovat a uložit podepsané
	 * PDF s obrázkem podpisu (getImageDataUri()) a otiskem (getDocumentHash()). Volá se před
	 * flush - když vyhodí výjimku, podpis zůstane neověřený a podepsat jde znovu.
	 * null = k podpisu žádné PDF nevzniká.
	 */
	public function createSignedPdf(SignedDocument $document): ?SignedPdf;

	/** Vše je uloženo a žádost vyřízená - navazující kroky projektu (e-mail, změna stavu záznamu) */
	public function onSigned(SignedDocument $document): void;
}
