<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

interface BaseFormFactoryInterface
{
	public function create(): BaseForm;
}
