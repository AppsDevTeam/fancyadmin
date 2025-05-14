<?php

namespace ADT\FancyAdmin\UI\Forms;

use ADT\FancyAdmin\UI\Forms\Base\BaseForm;

interface BaseFormFactoryInterface
{
	public function create(): BaseForm;
}
