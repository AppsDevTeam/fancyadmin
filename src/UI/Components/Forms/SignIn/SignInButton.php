<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\SignIn;

/**
 * Další tlačítko na přihlašovací stránce pod formulářem (např. přihlášení přes externí SSO).
 */
final readonly class SignInButton
{
	/**
	 * @param string $label překladový klíč popisku
	 * @param string $destination cíl odkazu pro plink, např. ':Portal:TmobileSsoAuth:in'
	 * @param array<string, mixed> $args parametry odkazu
	 * @param string|null $icon třídy ikonky, např. 'fa-solid fa-mobile'
	 */
	public function __construct(
		public string $label,
		public string $destination,
		public array $args = [],
		public ?string $icon = null,
	) {
	}
}
