<?php

namespace ADT\FancyAdmin\UI\Forms\SignIn;

use ADT\FancyAdmin\UI\Forms\SignIn\SignInForm;

interface SignInFormFactory
{
	public function create(): SignInForm;
}
