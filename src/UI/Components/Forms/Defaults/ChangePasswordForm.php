<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\ChangePassword\ChangePasswordFormTrait;

class ChangePasswordForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\ChangePassword\ChangePasswordForm
{
	use ChangePasswordFormTrait;
}
