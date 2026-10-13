<?php

declare(strict_types=1);

use Tester\Assert;

/**
 * Traity komponent (formulare, gridy) cteji prihlaseneho uzivatele z vlastnosti, kterou
 * si samy deklaruji (SecurityUserInject => $_securityUser). Cteni nedeklarovane vlastnosti
 * se projevi az za behu (BaseFormTrait cetl $this->securityUser a kazdy formular
 * prihlaseneho uzivatele spadl na MemberAccessException).
 */

require __DIR__ . '/bootstrap.php';


const COMPONENTS_DIR = __DIR__ . '/../src/UI/Components';

/** @return list<string> nazvy vlastnosti vcetne traitu, ktere trait pouziva */
function traitProperties(ReflectionClass $trait): array
{
	$names = array_map(fn(ReflectionProperty $p) => $p->getName(), $trait->getProperties());
	foreach ($trait->getTraits() as $used) {
		$names = array_merge($names, traitProperties($used));
	}

	return $names;
}


test('traity komponent ctou jen deklarovanou vlastnost se SecurityUser', function () {
	$missing = [];

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(COMPONENTS_DIR)) as $file) {
		if (!$file->isFile() || $file->getExtension() !== 'php') {
			continue;
		}
		$source = (string) file_get_contents($file->getPathname());
		if (!preg_match('~^trait\s+(\w+)~m', $source, $traitMatch) || !preg_match('~^namespace\s+([^;]+);~m', $source, $nsMatch)) {
			continue;
		}
		preg_match_all('~\$this->(_?securityUser)\b(?!\s*\()~', $source, $reads);
		if ($reads[1] === []) {
			continue;
		}

		// $_securityUser muze dodat i zakladni trait komponenty, se kterym se trait
		// vzdy kombinuje (ApiKeyGridTrait s BaseGridTrait apod.)
		$properties = array_merge(
			traitProperties(new ReflectionClass($nsMatch[1] . '\\' . $traitMatch[1])),
			traitProperties(new ReflectionClass(ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait::class)),
			traitProperties(new ReflectionClass(ADT\FancyAdmin\UI\Components\Grids\BaseGridTrait::class)),
		);
		foreach (array_unique($reads[1]) as $name) {
			// $securityUser bez podtrzitka knihovna nedeklaruje nikde
			if ($name === 'securityUser' || !in_array($name, $properties, true)) {
				$missing[] = $traitMatch[1] . '::$' . $name;
			}
		}
	}

	Assert::same([], $missing);
});
