<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults\Backoffice;

use ADT\FancyAdmin\UI\Presenters\Defaults\AuthPresenter;
use ADT\FancyAdmin\UI\Presenters\Acl\AclPresenterTrait;

class AclPresenter extends AuthPresenter
{
	use AclPresenterTrait;
}
