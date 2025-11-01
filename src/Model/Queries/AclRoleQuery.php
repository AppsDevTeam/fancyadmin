<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface AclRoleQuery extends BaseQuery
{
	public function byName(string $name): static;
	public function byIsAdmin(bool $isAdmin): static;
	public function byIsIdentity(bool $isIdentity): static;
	public function byIsProfile(bool $isProfile): static;
}