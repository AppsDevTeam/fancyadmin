<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Label
{
	public function __construct(
		public readonly string $translationKey,
	) {
	}
}
