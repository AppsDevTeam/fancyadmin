<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use Attribute;

/**
 * Signál presenteru, který odpovídá sám (JSON, soubor) a nepotřebuje nic z akce ani renderu.
 *
 * Nette před signálem volá action<Akce>() aktuální stránky a po něm render<View>() - signál
 * z karty záznamu (AI widget, kalendář, hledání) by jinak pokaždé skládal celou stránku, i když
 * odpoví pár řádky JSONu. Presenter s BasePresenterTrait akci i render u takového signálu přeskočí.
 * startup() a kontrola přístupu běží dál.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class SkipActionAttribute
{
}
