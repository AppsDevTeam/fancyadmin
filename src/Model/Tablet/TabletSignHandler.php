<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\DocumentSignature;
use ADT\FancyAdmin\Model\Entities\SignedDocument;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;

/**
 * Co k podpisu dokumentů na tabletu dodává projekt (docs/tablets.md, Podpis dokumentu).
 * Stránku s náhledy a místy pro podpis, uložení podpisů s biometrikou, důkazní záznamy,
 * vložení podpisů do PDF i evidenci dělá fancyadmin (TabletSigning) - projekt jen řekne, co se
 * podepisuje, a uloží podepsaná PDF.
 *
 * Co se podepisuje, pozná z žádosti: getType(), getSubjectId(), getData().
 */
interface TabletSignHandler
{
	/**
	 * Dokumenty, které tablet k žádosti ukáže (v pořadí navigace). null nebo prázdný seznam =
	 * žádost už nejde podepsat (záznam mezitím zanikl, je už podepsaný jinde …) - tablet se
	 * vrátí na čekací stránku a žádost se zruší.
	 *
	 * @return list<TabletSignDocument>|null
	 */
	public function getDocuments(TabletSignRequest $request): ?array;

	/**
	 * Co se podepisuje - uloží se do důkazního záznamu podpisu a jeho otisk to tak pokrývá: kdo
	 * podepisuje, otisky originálů … Jen hodnoty serializovatelné do JSON. Dokumenty, místa
	 * a otisky PDF, do kterých se podpis vložil, přidá fancyadmin sám.
	 *
	 * @return array<mixed>
	 */
	public function getEvidenceSubject(DocumentSignature $signature): array;

	/**
	 * Podpisy jsou ověřené, důkazní záznamy a jejich otisky nastavené: uložit podepsané PDF
	 * dokumentu. $stampedPdf je PDF dokumentu s podpisy vloženými do označených míst - stačí
	 * ho uložit, případně do něj doplnit otisky podpisů ($signatures, getDocumentHash()).
	 * U dokumentu bez označených míst je null a PDF si projekt vygeneruje sám (obrázek podpisu
	 * getImageDataUri()). Volá se před flush - když vyhodí výjimku, žádost zůstane otevřená
	 * a podepsat jde znovu. null = k dokumentu žádné PDF nevzniká.
	 *
	 * @param list<DocumentSignature> $signatures podpisy vložené do dokumentu
	 */
	public function createSignedPdf(SignedDocument $document, ?string $stampedPdf, array $signatures): ?SignedPdf;

	/**
	 * Vše je uloženo a žádost vyřízená - navazující kroky projektu (e-mail, změna stavu záznamu,
	 * uložení obrázku podpisu ke klientovi - getImageDataUri() podpisu)
	 *
	 * @param list<SignedDocument> $documents
	 * @param list<DocumentSignature> $signatures podpisy v pořadí, v jakém je podepisující vložil
	 */
	public function onSigned(TabletSignRequest $request, array $documents, array $signatures): void;
}
