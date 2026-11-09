<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

/**
 * Tablety (docs/tablets.md). Čekací stránka tabletu běží bez přihlášeného uživatele -
 * TabletService proto volá disableSecurityFilter().
 */
interface TabletQuery extends BaseQuery
{
	public function byPairingTokenHash(string $hash): static;
	public function byDeviceSecretHash(string $hash): static;
	public function byCanReceiveForms(): static;
}
