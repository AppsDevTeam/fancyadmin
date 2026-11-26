<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

/** Druh uloženého filtru gridu (GridFilter::getType()). */
enum GridFilterType: string
{
	/** Pokročilé hledání datagrid-components - hodnota jsou podmínky hledání */
	case Advanced = 'advanced';

	/** Sestava z lišty filtrů (ColumnFiltersTrait) - hodnota jsou filtry gridu, k nim nastavení sloupců */
	case Preset = 'preset';
}
