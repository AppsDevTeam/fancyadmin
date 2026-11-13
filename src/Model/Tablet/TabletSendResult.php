<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\TabletSignRequest;

/**
 * Výsledek TabletService::send(): založená žádost a hláška pro obsluhu.
 */
final class TabletSendResult
{
	public function __construct(
		/** null = nic se neposlalo (tablet není spárovaný) */
		public readonly ?TabletSignRequest $request,
		/** Co má obsluha udělat, null = tablet dostal notifikaci */
		public readonly ?string $message,
	) {
	}

	/** Push odešel - tablet ukáže notifikaci a otevřená čekací stránka formulář rovnou otevře */
	public function isNotified(): bool
	{
		return $this->request !== null && $this->message === null;
	}
}
