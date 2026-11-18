<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

/** Barevný tón čísla nebo dlaždice (ikona a zvýraznění), mapuje se na barvy Bootstrapu. */
enum DashboardTone: string
{
	case Neutral = 'neutral';
	case Primary = 'primary';
	case Success = 'success';
	case Warning = 'warning';
	case Danger = 'danger';
	case Info = 'info';
}
