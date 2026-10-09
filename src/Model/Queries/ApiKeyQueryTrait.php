<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;

trait ApiKeyQueryTrait
{
	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}

	/**
	 * Přesně tenhle účet, nebo klíče bez účtu (globální) při null.
	 *
	 * Ne byAccount() z DefaultFilters: ten pouští i klíče podúčtů, protože slouží
	 * k omezení viditelnosti. Tady jde o skupinu, ve které se jméno nesmí opakovat,
	 * a ta je daná přesnou shodou - stejně jako unikátní index nad (name, account_key).
	 */
	public function byAccountOrGlobal(int|Account|null $account): static
	{
		return $this->by('account', $account);
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
