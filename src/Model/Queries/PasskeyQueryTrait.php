<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;

trait PasskeyQueryTrait
{
	public function byCredentialId(string $credentialId): static
	{
		return $this->by('credentialId', $credentialId);
	}

	public function byIdentity(Identity $identity): static
	{
		return $this->by('identity', $identity);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('createdAt', 'ASC');
	}
}
