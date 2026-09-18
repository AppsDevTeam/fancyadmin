<?php

declare(strict_types=1);

use Tester\Assert;

/**
 * Celistvost balicku.
 *
 * Zachytne to, co jednotlive testy minou: trida, ktera se po prejmenovani nenacte,
 * namespace mimo PSR-4 nebo sablona, na kterou kod ukazuje, ale uz neexistuje.
 */

require __DIR__ . '/bootstrap.php';


const SRC_DIR = __DIR__ . '/../src';

/** Migrace dedi z Doctrine\Migrations\AbstractMigration, coz neni zavislost balicku. */
const NENACITATELNE = ['ADT\FancyAdmin\Migrations\Version20260329120000'];

/** @return list<SplFileInfo> */
function sourceFiles(string $extension = 'php'): array
{
	$files = [];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRC_DIR)) as $file) {
		if ($file->isFile() && $file->getExtension() === $extension) {
			$files[] = clone $file;
		}
	}

	usort($files, fn(SplFileInfo $a, SplFileInfo $b) => strcmp($a->getPathname(), $b->getPathname()));

	return $files;
}

/** @return array<string, array{kind: string, file: SplFileInfo}> */
function declaredTypes(): array
{
	$types = [];

	foreach (sourceFiles() as $file) {
		$source = (string) file_get_contents($file->getPathname());

		if (!preg_match('~^namespace\s+([^;{]+)[;{]~m', $source, $namespace)) {
			continue;
		}

		preg_match_all('~^(?:final\s+|abstract\s+|readonly\s+)*(class|interface|trait|enum)\s+(\w+)~m', $source, $matches, PREG_SET_ORDER);
		foreach ($matches as [, $kind, $name]) {
			$types[trim($namespace[1]) . '\\' . $name] = ['kind' => $kind, 'file' => $file];
		}
	}

	return $types;
}


test('balicek obsahuje ocekavany pocet typu', function () {
	// Pojistka proti tomu, ze by se hledani rozbilo a testy nize kontrolovaly prazdno.
	Assert::true(count(declaredTypes()) > 200);
});


test('vsechny tridy, rozhrani, traity i enumy se daji nacist', function () {
	$problems = [];

	foreach (declaredTypes() as $type => ['kind' => $kind, 'file' => $file]) {
		if (in_array($type, NENACITATELNE, true)) {
			continue;
		}

		try {
			$loaded = match ($kind) {
				'class' => class_exists($type),
				'interface' => interface_exists($type),
				'trait' => trait_exists($type),
				'enum' => enum_exists($type),
			};

			if (!$loaded) {
				$problems[$type] = 'nenacetl se (' . $file->getFilename() . ')';
			}
		} catch (Throwable $e) {
			$problems[$type] = $e::class . ': ' . $e->getMessage();
		}
	}

	Assert::same([], $problems);
});

test('POZOR: migrace dedi z tridy, ktera neni zavislosti balicku', function () {
	// doctrine/migrations neni ani v require, ani v suggest - dodava ji az projekt.
	Assert::false(class_exists('Doctrine\Migrations\AbstractMigration'));
	Assert::true(is_file(SRC_DIR . '/Migrations/Version20260329120000.php'));
});


test('namespace odpovida umisteni souboru', function () {
	$problems = [];

	foreach (declaredTypes() as $type => ['file' => $file]) {
		$expectedPath = SRC_DIR . '/' . str_replace('\\', '/', substr($type, strlen('ADT\FancyAdmin\\'))) . '.php';

		if (realpath($expectedPath) !== realpath($file->getPathname())) {
			$problems[$type] = $file->getPathname();
		}
	}

	Assert::same([], $problems);
});


test('sablony, na ktere kod ukazuje pres __DIR__, existuji', function () {
	$missing = [];

	foreach (sourceFiles() as $file) {
		preg_match_all(
			'~__DIR__\s*\.\s*\'(/[^\']+\.latte)\'~',
			(string) file_get_contents($file->getPathname()),
			$matches,
		);

		foreach ($matches[1] as $template) {
			if (!is_file($file->getPath() . $template)) {
				$missing[] = $file->getFilename() . ' -> ' . $template;
			}
		}
	}

	Assert::same([], $missing);
});


test('zdrojove soubory nemaji BOM ani koncovky Windows', function () {
	$problems = [];

	foreach (array_merge(sourceFiles(), sourceFiles('latte')) as $file) {
		$source = (string) file_get_contents($file->getPathname());

		if (str_starts_with($source, "\xEF\xBB\xBF")) {
			$problems[] = 'BOM: ' . $file->getFilename();
		}
		if (str_contains($source, "\r\n")) {
			$problems[] = 'CRLF: ' . $file->getFilename();
		}
	}

	Assert::same([], $problems);
});


test('vsechny PHP soubory zacinaji otviracim tagem', function () {
	foreach (sourceFiles() as $file) {
		Assert::true(
			str_starts_with((string) file_get_contents($file->getPathname()), '<?php'),
			$file->getFilename(),
		);
	}
});
