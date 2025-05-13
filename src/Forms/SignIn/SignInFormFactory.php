<?php

namespace ADT\FancyAdmin\Forms\SignIn;

use ADT\FancyAdmin\Forms\SignIn\SignInForm;

interface SignInFormFactory
{
	public function create(): SignInForm;
}
