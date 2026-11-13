<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;

/**
 * Úložiště žádostí o podpis poslaných na tablety (TabletSignRequest). TabletService podle
 * nich pozná, co má tablet otevřít; výchozí implementace je DoctrineTabletSignRequests.
 */
interface TabletSignRequests
{
	/** Nová žádost pro tablet - neuložená (uloží ji add()) */
	public function create(Tablet $tablet, string $url, string $title, \DateTimeImmutable $now, \DateTimeImmutable $expiresAt): TabletSignRequest;

	public function add(TabletSignRequest $request): void;

	/** Nejnovější žádost, kterou si tablet ještě nevyzvedl a která nepropadla */
	public function findPending(Tablet $tablet, \DateTimeImmutable $now): ?TabletSignRequest;

	/** Žádost, kterou tablet právě zobrazuje (vyzvednutá, nepodepsaná, nezrušená) */
	public function findOpen(Tablet $tablet): ?TabletSignRequest;

	/**
	 * Žádosti tabletu, které ještě nejsou vyřízené: čekající na vyzvednutí i otevřené.
	 *
	 * @return list<TabletSignRequest>
	 */
	public function findUnfinished(Tablet $tablet, \DateTimeImmutable $now): array;
}
