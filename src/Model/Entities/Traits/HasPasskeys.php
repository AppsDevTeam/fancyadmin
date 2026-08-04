<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Passkey;

interface HasPasskeys
{
	/**
	 * @return Passkey[]
	 */
	public function getPasskeys(): array;
	public function getPasskeyUserHandle(): ?string;
	public function setPasskeyUserHandle(?string $passkeyUserHandle): static;
}
