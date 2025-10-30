<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Account;

interface AccountFormFactory
{
	public function create(): AccountForm;
}