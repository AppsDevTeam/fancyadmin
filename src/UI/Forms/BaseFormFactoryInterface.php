<?php

namespace ADT\FancyAdmin\UI\Forms;

interface BaseFormFactoryInterface
{
	public function create(): BaseForm;
}
