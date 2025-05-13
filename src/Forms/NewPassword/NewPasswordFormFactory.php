<?php

namespace ADT\FancyAdmin\Forms\NewPassword;

use ADT\FancyAdmin\Forms\NewPassword\NewPasswordForm;

interface NewPasswordFormFactory
{
	public function create(): NewPasswordForm;
}
