<?php

namespace ADT\FancyAdmin\UI\Forms\SignIn;

use App\UI\Portal\Components\Forms\SignIn\SignInForm;

interface SignInFormFactory
{
	public function create(): SignInForm;
}
