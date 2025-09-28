<?php

namespace ADT\FancyAdmin\UI\Components\Forms\NewPassword;

interface NewPasswordFormFactory
{
	public function create(): NewPasswordForm;
}
