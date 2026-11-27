<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;

/** Záznam exportu adt/exporter s autorem: export z gridu stáhne jen ten, kdo ho zadal (docs/datagrid-export.md). */
interface Export extends \ADT\Exporter\Model\Entities\Export, CreatedByNullableInterface
{
}
