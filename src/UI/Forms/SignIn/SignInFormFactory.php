<?php

namespace ADT\FancyAdmin\UI\Forms\SignIn;

interface SignInFormFactory
{
	public function create(): SignInForm;
}
