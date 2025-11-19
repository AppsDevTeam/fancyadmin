<?php

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

use ADT\FancyAdmin\Model\Entities\Identity;

interface NewPasswordFormFactory
{
	public function create(): NewPasswordForm;
}
