<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Configuration;

interface ConfigurationFormFactory
{
	public function create(): ConfigurationForm;
}