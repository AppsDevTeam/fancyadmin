<?php

namespace ADT\FancyAdmin\UI\Forms\NewPassword;

use ADT\FancyAdmin\UI\Forms\NewPassword\NewPasswordForm;

interface NewPasswordFormFactory
{
	public function create(): NewPasswordForm;
}
