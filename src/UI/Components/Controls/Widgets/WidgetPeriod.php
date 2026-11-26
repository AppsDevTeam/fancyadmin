<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use DateTimeImmutable;

/** Období widgetu (docs/widgets.md). Hodnota je zároveň to, co jde do URL. */
enum WidgetPeriod: string
{
	case TODAY = 'today';
	case YESTERDAY = 'yesterday';
	case THIS_WEEK = 'thisWeek';
	case LAST_7_DAYS = 'last7Days';
	case THIS_MONTH = 'thisMonth';
	case LAST_MONTH = 'lastMonth';
	case THIS_QUARTER = 'thisQuarter';
	case THIS_YEAR = 'thisYear';
	case LAST_12_MONTHS = 'last12Months';

	/** Neznámá hodnota z URL nemá shodit stránku, jen se nepoužije. */
	public static function fromUrl(?string $value, self $default): self
	{
		return $value !== null ? (self::tryFrom($value) ?? $default) : $default;
	}

	public function getTranslationKey(): string
	{
		return 'fcadmin.widgets.period.' . $this->value;
	}

	/**
	 * Začátek a konec období. Konec je výlučný (první okamžik po období), takže objednávka
	 * z 23:59:59 nespadne do dvou sousedních období zároveň.
	 *
	 * @return array{DateTimeImmutable, DateTimeImmutable}
	 */
	public function getRange(?DateTimeImmutable $now = null): array
	{
		$today = ($now ?? new DateTimeImmutable())->modify('midnight');
		$tomorrow = $today->modify('+1 day');

		return match ($this) {
			self::TODAY => [$today, $tomorrow],
			self::YESTERDAY => [$today->modify('-1 day'), $today],
			// V pondělí by `monday this week` skočilo na dnešek, proto přes `-N dnů`
			self::THIS_WEEK => [$today->modify('-' . ((int) $today->format('N') - 1) . ' days'), $tomorrow],
			self::LAST_7_DAYS => [$today->modify('-6 days'), $tomorrow],
			self::THIS_MONTH => [$today->modify('first day of this month'), $tomorrow],
			self::LAST_MONTH => [$today->modify('first day of last month'), $today->modify('first day of this month')],
			self::THIS_QUARTER => [self::quarterStart($today), $tomorrow],
			self::THIS_YEAR => [$today->setDate((int) $today->format('Y'), 1, 1), $tomorrow],
			// Tento měsíc a 11 celých měsíců před ním
			self::LAST_12_MONTHS => [$today->modify('first day of this month')->modify('-11 months'), $tomorrow],
		};
	}

	/**
	 * Stejně dlouhé období před zvoleným, na porovnání. Kalendářní období se posunou o svou jednotku
	 * a porovnají se ve stejných dnech (tento měsíc do dneška s 1. až N. dnem minulého měsíce); co
	 * by přesáhlo do zvoleného období, se zkrátí. Celý minulý měsíc se porovná s celým měsícem před
	 * ním, i když je jinak dlouhý. Dny (dnes, včera, 7 dní) jdou těsně před.
	 *
	 * @return array{DateTimeImmutable, DateTimeImmutable}
	 */
	public function getPreviousRange(?DateTimeImmutable $now = null): array
	{
		[$from, $to] = $this->getRange($now);
		$days = (int) $from->diff($to)->days;
		$shifted = fn(DateTimeImmutable $start): array => [$start, min($start->modify('+' . $days . ' days'), $from)];

		return match ($this) {
			self::TODAY, self::YESTERDAY, self::LAST_7_DAYS => $shifted($from->modify('-' . $days . ' days')),
			self::THIS_WEEK => $shifted($from->modify('-7 days')),
			self::THIS_MONTH => $shifted($from->modify('first day of last month')),
			self::LAST_MONTH => [$from->modify('first day of last month'), $from],
			self::THIS_QUARTER => $shifted($from->modify('-3 months')),
			self::THIS_YEAR, self::LAST_12_MONTHS => $shifted($from->modify('-1 year')),
		};
	}

	private static function quarterStart(DateTimeImmutable $date): DateTimeImmutable
	{
		$month = intdiv((int) $date->format('n') - 1, 3) * 3 + 1;

		return $date->setDate((int) $date->format('Y'), $month, 1);
	}
}
