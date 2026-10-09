<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\UI\Components\Grids\ChangeLog\ChangeLogGridTrait;
use Nette\Localization\Translator;

/**
 * Minimalni uzivatel ChangeLogGridTrait pro test vykreslovani changesetu.
 *
 * Cely grid se stavet nemusi: renderChangeSet() potrebuje jen translator a seznam
 * maskovanych properties, ktery si projekt prepisuje.
 */
final class TestChangeLogGrid
{
	use ChangeLogGridTrait {
		ChangeLogGridTrait::initGrid as private traitInitGrid;
	}

	/** @param list<string> $maskedProperties */
	public function __construct(
		private readonly Translator $translator,
		private readonly array $maskedProperties = [],
	) {
	}

	public function getTranslator(): Translator
	{
		return $this->translator;
	}

	/** @return list<string> */
	protected function getMaskedProperties(): array
	{
		return $this->maskedProperties;
	}
}
