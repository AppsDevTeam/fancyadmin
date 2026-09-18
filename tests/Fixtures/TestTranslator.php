<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Nette\Localization\Translator;
use Stringable;

/**
 * Preklad vraci bud podstrceny retezec, nebo samotny klic - diky tomu je v assertech
 * videt, ktery preklad se pouzil.
 */
final class TestTranslator implements Translator
{
	/** @var list<array{string, array}> */
	public array $calls = [];

	/** @param array<string, string> $messages */
	public function __construct(private readonly array $messages = [])
	{
	}

	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		$key = (string) $message;
		$this->calls[] = [$key, $parameters];

		return $this->messages[$key] ?? $key;
	}
}
