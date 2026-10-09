<?php

namespace ADT\FancyAdmin\UI\Components\Forms\TwoFactor;

interface TwoFactorFormFactory
{
	public function create(): TwoFactorForm;
}
