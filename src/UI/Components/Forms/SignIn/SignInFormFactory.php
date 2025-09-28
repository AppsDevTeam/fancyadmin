<?php

namespace ADT\FancyAdmin\UI\Components\Forms\SignIn;

interface SignInFormFactory
{
	public function create(): SignInForm;
}
