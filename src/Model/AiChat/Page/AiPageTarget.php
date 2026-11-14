<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat\Page;

/** Kam nástroj `open_page` uživatele pošle. */
final class AiPageTarget
{
	/**
	 * @param string $destination presenter a akce v modulu backoffice, např. `Client:detail`
	 * @param array<string, mixed> $args parametry odkazu
	 * @param string $title popisek tlačítka v chatu - u osoby kotva (`[KLIENT #12]`), ne jméno:
	 *                      AI ho vidí, jméno doplní adt/ai-chat až uživateli
	 */
	public function __construct(
		public readonly string $destination,
		public readonly array $args = [],
		public readonly string $title = '',
	) {
	}
}
