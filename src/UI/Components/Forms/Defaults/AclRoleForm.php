<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\AclRole\AclRoleFormTrait;

class AclRoleForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\AclRole\AclRoleForm
{
	use AclRoleFormTrait;
}
