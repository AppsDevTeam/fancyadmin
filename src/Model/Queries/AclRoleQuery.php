<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface AclRoleQuery extends BaseQuery
{
	public function byName(string $name): static;
	public function byIsAdmin(bool $isAdmin): static;
	public function byType(AclRoleTypeEnum $aclRoleType): static;
	public function byContext(?string $context): static;
}