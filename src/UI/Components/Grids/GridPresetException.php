<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

use RuntimeException;

/** Sestavu nejde uložit (duplicitní název) nebo ji uživatel nesmí měnit. Zpráva je překladový klíč. */
final class GridPresetException extends RuntimeException
{
	public const int FORBIDDEN = 403;
	public const int NOT_FOUND = 404;
	public const int INVALID = 422;
}
