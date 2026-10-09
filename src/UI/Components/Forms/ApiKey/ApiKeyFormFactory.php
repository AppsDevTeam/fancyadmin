<?php

namespace ADT\FancyAdmin\UI\Components\Forms\ApiKey;

interface ApiKeyFormFactory
{
	public function create(): ApiKeyForm;
}
