<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestHttpResponse;
use ADT\FancyAdmin\Tests\Fixtures\TestPresenter;
use ADT\Forms\BootstrapFormRenderer;
use Nette\Application\UI\Form;
use Nette\DI\Config\Loader;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * Inline skripty vs. CSP ze security.neon.
 *
 * security.neon posila `script-src 'nonce' 'self'`, takze prohlizec spusti jen skripty
 * ze stejneho originu a inline skripty s nonce. Inline skript bez nonce se tise zablokuje
 * a na strance pak neco nefunguje, aniz by to kdokoli poznal (chybove hlasky formularu
 * se nezobrazily, Keycloak se neinicializoval).
 */

require __DIR__ . '/bootstrap.php';


const SRC_DIR = __DIR__ . '/../src';

/** @return list<string> */
function packageFiles(string $extension): array
{
	$files = [];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRC_DIR)) as $file) {
		if ($file->isFile() && $file->getExtension() === $extension) {
			$files[] = $file->getPathname();
		}
	}

	sort($files);

	return $files;
}

/** @return list<string> otviraci tagy inline skriptu (bez src a mimo JSON data bloky) */
function inlineScriptTags(string $source): array
{
	preg_match_all('~<script\b[^>]*>~i', $source, $matches);

	return array_values(array_filter(
		$matches[0],
		fn(string $tag) => !preg_match('~\ssrc\s*=~i', $tag) && !preg_match('~type\s*=\s*["\']?application/(ld\+)?json~i', $tag),
	));
}

function relativePath(string $path): string
{
	return substr($path, strlen(realpath(SRC_DIR)) + 1);
}

/** @return array<string, string> */
function securityCsp(): array
{
	return new Loader()->load(__DIR__ . '/../config/security.neon')['http']['csp'];
}


test('security.neon povoluje skripty jen ze stejneho originu a s nonce', function () {
	$scriptSrc = securityCsp()['script-src'];

	// Nette\Bridges\HttpDI\HttpExtension nahradi 'nonce' za 'nonce-<nahodna hodnota>'.
	Assert::contains("'nonce'", $scriptSrc);
	Assert::contains("'self'", $scriptSrc);
	Assert::notContains('unsafe-inline', $scriptSrc);
	Assert::notContains('unsafe-eval', $scriptSrc);
});


test('kazdy inline skript v sablonach balicku ma n:nonce', function () {
	$missing = [];

	foreach (packageFiles('latte') as $file) {
		foreach (inlineScriptTags((string) file_get_contents($file)) as $tag) {
			if (!preg_match('~\sn:nonce\b~', $tag)) {
				$missing[] = relativePath($file) . ': ' . $tag;
			}
		}
	}

	Assert::same([], $missing);
});


test('sablony, ktere inline skript potrebuji, ho maji s n:nonce', function () {
	// Pojistka, ze test vyse opravdu neco kontroluje - tyhle tri sablony inline skript maji.
	foreach (['UI/Presenters/@layout.latte', 'UI/Presenters/Keycloak/out.latte', 'UI/Presenters/Keycloak/silentCheckSso.latte'] as $template) {
		$tags = inlineScriptTags((string) file_get_contents(SRC_DIR . '/' . $template));

		Assert::count(1, $tags, $template);
		Assert::contains('n:nonce', $tags[0], $template);
	}
});


test('PHP kod balicku nesklada inline skripty', function () {
	// Skript poskladany v PHP se k n:nonce nedostane, nonce by si musel vytahnout z hlavicky sam.
	$found = [];

	foreach (packageFiles('php') as $file) {
		if (inlineScriptTags((string) file_get_contents($file)) !== []) {
			$found[] = relativePath($file);
		}
	}

	Assert::same([], $found);
});


test('inlineScriptTags pozna inline skript a vynecha externi a JSON bloky', function () {
	Assert::same(['<script>'], inlineScriptTags('<script>alert(1)</script>'));
	Assert::same(['<script n:nonce>'], inlineScriptTags('<script n:nonce>x()</script>'));
	Assert::same(['<SCRIPT type="text/javascript">'], inlineScriptTags('<SCRIPT type="text/javascript">x()</SCRIPT>'));
	Assert::same([], inlineScriptTags('<script src="/app.js"></script>'));
	Assert::same([], inlineScriptTags('<script type="module" src={$url}></script>'));
	Assert::same([], inlineScriptTags('<script type="application/json">{}</script>'));
	Assert::same([], inlineScriptTags('<script type="application/ld+json">{}</script>'));
	Assert::same([], inlineScriptTags('<noscript>bez JS</noscript>'));
});


test('s CSP ze security.neon vynecha renderer inline skript u chyb formulare', function () {
	// Chyby prichazeji v AJAX snippetu s nonce jineho requestu, skript by prohlizec zablokoval.
	// Tridu is-invalid pak nastavi SubmitForm z adt-js-components podle data-adt-errors-for.
	$csp = [];
	foreach (securityCsp() as $directive => $value) {
		$csp[] = $directive . ' ' . str_replace("'nonce'", "'nonce-q1w2e3r4T5Y6u7i8O9p0+/=='", $value);
	}

	$httpResponse = new TestHttpResponse();
	$httpResponse->setHeader('Content-Security-Policy', implode('; ', $csp));
	$presenter = new TestPresenter();
	$presenter->injectPrimary(new Request(new UrlScript('https://localhost/')), $httpResponse);

	$form = new Form();
	$presenter->addComponent($form, 'form');
	$renderer = new BootstrapFormRenderer($form);
	$form->setRenderer($renderer);
	$form->addPassword('password', 'Heslo');
	BootstrapFormRenderer::makeBootstrap($form);
	$form['password']->addError('Heslo je slabe.');

	$html = $renderer->renderErrors($form['password']);

	Assert::notContains('<script', $html);
	Assert::contains('data-adt-errors-for="frm-form-password"', $html);
	Assert::contains('Heslo je slabe.', $html);
});
