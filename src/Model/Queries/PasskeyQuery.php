<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface PasskeyQuery extends BaseQuery
{
	public function byCredentialId(string $credentialId): static;
	public function byIdentity(Identity $identity): static;
}
