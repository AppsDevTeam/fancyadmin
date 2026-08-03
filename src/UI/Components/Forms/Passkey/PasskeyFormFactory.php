<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Passkey;

interface PasskeyFormFactory
{
	public function create(): PasskeyForm;
}
