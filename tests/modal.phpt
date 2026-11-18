<?php

declare(strict_types=1);

use ADT\FancyAdmin\UI\Components\Modal\Modal;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Localization\Translator;
use Tester\Assert;

/**
 * Modal (src/UI/Components/Modal/modal.latte, docs/modal.md): hlavička s ikonou, velikost, patička jen s obsahem,
 * formulář přes tělo i patičku a atributy pro LazyComponentsTrait.
 */

require __DIR__ . '/bootstrap.php';

final class ModalTranslator implements Translator
{
	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		return '[' . $message . ']';
	}
}

function renderModal(string $modal): string
{
	$file = tempnam(sys_get_temp_dir(), 'modal') . '.latte';
	file_put_contents($file, '{import ' . Modal::class . "::TEMPLATE}\n" . $modal);

	$latte = new Engine();
	$latte->setTempDirectory(null);
	$latte->addExtension(new TranslatorExtension(new ModalTranslator()));

	try {
		return $latte->renderToString($file);
	} finally {
		unlink($file);
	}
}

test('hlavička s ikonou a titulkem, užší scrollovatelný dialog, bez patičky', function () {
	$html = renderModal("{embed modal, id: 'sms', title: 'Odeslat SMS', icon: 'comment-sms'}{block body}<p>tělo</p>{/block}{/embed}");

	Assert::match('%A%<div class="modal fade" id="sms" tabindex="-1" aria-labelledby="smsTitle" aria-hidden="true"%A%', $html);
	Assert::contains('<div class="modal-dialog modal-dialog-scrollable modal-dialog-narrow">', $html);
	Assert::match('%A%<h2 class="modal-title h5" id="smsTitle">%A%<i class="fa-regular fa-comment-sms me-2" aria-hidden="true"></i>Odeslat SMS%A%', $html);
	Assert::contains('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="[fcadmin.modal.close]"></button>', $html);
	Assert::match('%A%<div class="modal-body">%A%<p>tělo</p>%A%', $html);
	Assert::notContains('modal-footer', $html);
	Assert::notContains('<form', $html);
	Assert::notContains('data-lazy-url', $html);
});

test('patička s tlačítkem Zavřít, vlastní velikost a plné třídy ikony', function () {
	$html = renderModal("{embed modal, id: 'x', title: 'X', icon: 'fa-solid fa-pen', size: 'xl', centered: true}{block body}b{/block}{block footer}{include modalClose}<button type=\"submit\" class=\"btn btn-primary\">Uložit</button>{/block}{/embed}");

	Assert::contains('<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-xl">', $html);
	Assert::contains('<i class="fa-solid fa-pen me-2" aria-hidden="true"></i>X', $html);
	Assert::match('%A%<div class="modal-footer">%A%<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">[fcadmin.modal.close]</button>%A%<button type="submit" class="btn btn-primary">Uložit</button>%A%', $html);
});

test('formulář obalí hlavičku, tělo i patičku', function () {
	$html = renderModal("{embed modal, id: 'f', title: 'F', form: [method: post, action: '/send', class: ajax]}{block body}<input name=\"a\">{/block}{block footer}<button type=\"submit\">OK</button>{/block}{/embed}");

	Assert::match('%A%<div class="modal-content">%A%<form method="post" action="/send" class="ajax">%A%<div class="modal-header">%A%<div class="modal-footer">%A%</form>%A%', $html);
});

test('líně načítaný modal nese adresu a stav načtení, otevření ze serveru data-adt-show-modal', function () {
	Assert::contains('data-lazy-url="/lazy"', renderModal("{embed modal, id: 'l', title: 'L', lazyUrl: '/lazy'}{block body}{/block}{/embed}"));
	Assert::notContains('data-lazy-state', renderModal("{embed modal, id: 'l', title: 'L', lazyUrl: '/lazy'}{block body}{/block}{/embed}"));
	Assert::contains('data-lazy-state="loaded"', renderModal("{embed modal, id: 'l', title: 'L', lazyUrl: '/lazy', lazyLoaded: true}{block body}{/block}{/embed}"));
	Assert::contains('data-adt-show-modal', renderModal("{embed modal, id: 's', title: 'S', show: true}{block body}{/block}{/embed}"));
	Assert::notContains('data-adt-show-modal', renderModal("{embed modal, id: 's', title: 'S'}{block body}{/block}{/embed}"));
	Assert::contains('<div class="modal-dialog modal-dialog-scrollable">', renderModal("{embed modal, id: 'w', title: 'W', size: ''}{block body}{/block}{/embed}"));
});
