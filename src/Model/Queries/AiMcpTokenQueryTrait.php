<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;

trait AiMcpTokenQueryTrait
{
	public function byIdentity(int|Identity $identity): static
	{
		return $this->by('identity', $identity);
	}

	/** Vyhledání podle čitelného tokenu (v databázi je uložený jeho SHA-256 otisk). */
	public function byRawToken(string $rawToken): static
	{
		return $this->by('tokenHash', ApiKeyHasher::hash($rawToken));
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('createdAt', 'DESC');
	}
}
