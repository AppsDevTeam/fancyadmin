<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat\Page;

/**
 * Stránka záznamu, kterou AI umí uživateli otevřít nástrojem `open_page` (karta klienta,
 * detail dokladu …) - stránky z menu nástroj zná sám. Projekt ji registruje jako službu,
 * fancyadmin si ji najde podle typu.
 *
 * Nástroj běží pod uživatelem, který s AI mluví (worker fronty), přihlášeným bez stavu -
 * resolve() má vrátit null i pro záznam, který uživatel nevidí (stejně jako neexistující).
 * Právo na presenter cíle (`<modul>.<presenter>`) kontroluje nástroj sám.
 */
interface AiPage
{
	/** Název, který AI posílá v `page` (malými písmeny, např. `patient`). */
	public function getName(): string;

	/** Pro AI: co je to za stránku a co je `id`, např. "card of one patient; id = patient.id from [PACIENT #id]". */
	public function getDescription(): string;

	/** Cíl pro záznam `id`, null = neexistuje nebo ho uživatel nevidí. */
	public function resolve(int $id): ?AiPageTarget;
}
