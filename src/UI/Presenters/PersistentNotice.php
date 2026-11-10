<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use InvalidArgumentException;
use Nette\HtmlStringable;

/**
 * Trvaly panel v layoutu portalu - upozorneni, ktere na rozdil od flash zpravy nezmizi, dokud
 * plati (treba „zmeny se odeslou v 14:30" s tlacitkem „Odeslat hned"). Presenter ho vraci
 * z {@see BasePresenterTrait::getPersistentNotice()}, layout ho vykresli na kazde strance
 * prihlasene casti a po ajaxu ho sam prekresli.
 *
 * Text i popisek odkazu jsou klice prekladu (jako u flash zprav), text dostane
 * $parameters. Vysledek se escapuje; HTML jde jen jako {@see HtmlStringable} (Nette\Utils\Html),
 * ktere se nepreklada ani neescapuje.
 */
final readonly class PersistentNotice
{
	/**
	 * @param array<string, mixed> $parameters parametry prekladu textu
	 * @param string|null $link hotova adresa odkazu, napr. `$this->link('sendNow!')`
	 * @param bool $ajax odkaz se posle ajaxem (trida `ajax`); panel i flash zpravy se pak prekresli samy
	 */
	public function __construct(
		public string|HtmlStringable $message,
		public array $parameters = [],
		public ?string $link = null,
		public ?string $linkLabel = null,
		public bool $ajax = false,
	) {
		if ($this->link !== null && $this->linkLabel === null) {
			throw new InvalidArgumentException('PersistentNotice: odkaz potrebuje popisek (linkLabel).');
		}
	}
}
