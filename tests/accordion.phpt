<?php

declare(strict_types=1);

use ADT\FancyAdmin\UI\Components\Accordion\Accordion;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Localization\Translator;
use Tester\Assert;

/**
 * Sekce accordionu (src/UI/Components/Accordion/accordion.latte, docs/accordion.md): markup, na kterem stoji
 * assets/js/accordion.js - id sekce, collapse s aria, odznak napojeny na pole a blok body s promennymi stranky.
 */

require __DIR__ . '/bootstrap.php';


final class AccordionTranslator implements Translator
{
	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		return '[' . $message . ']';
	}
}


/** @param array<string, mixed> $params */
function renderAccordion(string $sections, array $params = []): string
{
	$file = tempnam(sys_get_temp_dir(), 'accordion') . '.latte';
	file_put_contents($file, '{import ' . Accordion::class . "::TEMPLATE}\n<div class=\"accordion-sections\" data-single>" . $sections . '</div>');

	$latte = new Engine();
	$latte->setTempDirectory(null);
	$latte->addExtension(new TranslatorExtension(new AccordionTranslator()));

	try {
		return $latte->renderToString($file, $params);
	} finally {
		unlink($file);
	}
}


test('sekce ma titulek, popis, collapse s aria a obsah bloku body s promennymi stranky', function () {
	$html = renderAccordion(
		"{embed accordionSection, id: 'paid', title: 'Zaplaceno', description: 'E-mail po zaplacení'}{block body}<form>{\$formName}</form>{/block}{/embed}",
		['formName' => 'paidForm'],
	);

	Assert::contains('data-accordion-section="paid"', $html);
	Assert::contains('<span class="accordion-section-title">Zaplaceno</span>', $html);
	Assert::contains('<span class="accordion-section-description">E-mail po zaplacení</span>', $html);
	Assert::match('%A%class="accordion-section-header collapsed" data-bs-toggle="collapse" data-bs-target="#accordion-section-paid" aria-expanded="false" aria-controls="accordion-section-paid"%A%', $html);
	Assert::match('%A%<div id="accordion-section-paid" class="collapse">%A%<form>paidForm</form>%A%', $html);
	Assert::notContains('data-accordion-badge', $html);
});


test('otevrena sekce ma show a aria-expanded true', function () {
	$html = renderAccordion("{embed accordionSection, id: 'a', title: 'A', open: true}{block body}x{/block}{/embed}");

	Assert::contains('class="accordion-section is-open"', $html);
	Assert::contains('class="accordion-section-header" data-bs-toggle="collapse"', $html);
	Assert::contains('aria-expanded="true"', $html);
	Assert::contains('class="collapse show"', $html);
});


test('pevny odznak je sedy', function () {
	$html = renderAccordion("{embed accordionSection, id: 'a', title: 'A', badge: 'Nové'}{block body}x{/block}{/embed}");

	Assert::contains('<span class="accordion-section-badge badge text-bg-secondary" data-accordion-badge>Nové</span>', $html);
	Assert::notContains('data-accordion-badge-source', $html);
});


test('odznak napojeny na pole nese texty pro JS a vychozi stav z entity', function () {
	$active = renderAccordion("{embed accordionSection, id: 'cash', title: 'Dobírka', badgeSource: 'isActive', badgeActive: true}{block body}x{/block}{/embed}");
	$inactive = renderAccordion("{embed accordionSection, id: 'cash', title: 'Dobírka', badgeSource: 'isActive', badgeActive: false, badgeOn: 'Zapnuto', badgeOff: 'Vypnuto'}{block body}x{/block}{/embed}");

	Assert::contains('data-accordion-badge-source="isActive" data-accordion-badge-on="[fcadmin.accordion.active]" data-accordion-badge-off="[fcadmin.accordion.inactive]"', $active);
	Assert::contains('<span class="accordion-section-badge badge text-bg-success" data-accordion-badge>[fcadmin.accordion.active]</span>', $active);
	Assert::contains('data-accordion-badge-on="Zapnuto" data-accordion-badge-off="Vypnuto"', $inactive);
	Assert::contains('<span class="accordion-section-badge badge text-bg-secondary" data-accordion-badge>Vypnuto</span>', $inactive);
});


test('titulek a popis se escapuji', function () {
	$html = renderAccordion("{embed accordionSection, id: 'a', title: '<b>A</b>', description: '<i>d</i>'}{block body}x{/block}{/embed}");

	Assert::contains('&lt;b&gt;A&lt;/b&gt;', $html);
	Assert::contains('&lt;i&gt;d&lt;/i&gt;', $html);
	Assert::notContains('<b>', $html);
});
