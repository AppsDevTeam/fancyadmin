<?php

namespace ADT\FancyAdmin\UI\Forms\NewPassword;

interface NewPasswordFormFactory
{
	public function create(): NewPasswordForm;
}
