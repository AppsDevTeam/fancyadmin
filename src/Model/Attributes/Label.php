<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Label
{
	public function __construct(
		public readonly string $translationKey,
	) {
	}
}
