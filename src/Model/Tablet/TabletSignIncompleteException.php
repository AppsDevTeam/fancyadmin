<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

/**
 * Stránka podpisu poslala podpisy, které nepokrývají přesně místa dokumentů žádosti
 * (TabletSigning::sign()) - některé místo chybí, nebo podpis patří místu, které dokument nemá
 * (dokument se mezitím změnil). Podepisující musí podepsat znovu.
 */
final class TabletSignIncompleteException extends \RuntimeException
{
}
