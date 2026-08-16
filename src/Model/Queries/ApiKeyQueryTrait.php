<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Security\ApiKeyHasher;

trait ApiKeyQueryTrait
{
	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}

	/**
	 * Vyhledání podle čitelného klíče (v databázi je uložený jeho SHA-256 otisk).
	 */
	public function byRawKey(string $rawKey): static
	{
		return $this->by('key', ApiKeyHasher::hash($rawKey));
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}
