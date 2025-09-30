<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

interface AclResourceNameEnum
{
	/** @return static */
	public static function from(string|int $value): static;

	/** @return static|null */
	public static function tryFrom(string|int $value): ?static;

	/** @return string[]|int[] */
	public static function values(): array;
}
