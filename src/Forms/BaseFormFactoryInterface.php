<?php

namespace ADT\FancyAdmin\Form;

use ADT\FancyAdmin\Forms\Base\BaseForm;

interface BaseFormFactoryInterface
{
	public function create(): BaseForm;
}
