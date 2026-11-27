<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use Nette\Http\Session;

/** Zvolené období mřížky v relaci uživatele (sekce `fancyadmin.widgets.period`). */
final class SessionWidgetPeriodStorage implements WidgetPeriodStorage
{
	private const SECTION = 'fancyadmin.widgets.period';

	public function __construct(
		private readonly Session $session,
	) {
	}

	public function get(string $key): ?string
	{
		$value = $this->session->getSection(self::SECTION)->get($key);
		return is_string($value) ? $value : null;
	}

	public function set(string $key, string $period): void
	{
		$this->session->getSection(self::SECTION)->set($key, $period);
	}
}
