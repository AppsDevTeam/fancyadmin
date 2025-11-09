<?php

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

use ADT\FancyAdmin\Model\Entities\Identity;

interface NewPasswordFormFactory
{
	public function create(Identity $identity): NewPasswordForm;
}
