<?php

declare(strict_types=1);

namespace FancyAdminTests\Fixtures\EntityScan\Model\Entities;

/**
 * Zástupce za Identity - findProjectEntityClasses() bere rozhraní parametrem, takže fixture
 * nemusí tahat celý Doctrine stack jen kvůli desítkám metod skutečného Identity.
 */
interface ScanMarker
{
}
