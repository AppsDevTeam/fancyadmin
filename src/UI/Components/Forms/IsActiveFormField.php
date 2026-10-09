<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\Forms\Form;
use ADT\Forms\StaticContainer;

trait IsActiveFormField
{
	public function addIsActiveField(Form|StaticContainer $form, string $label = 'fcadmin.forms.user.labels.isActive'): void
	{
		$form->addCheckbox('isActive', $label)
			->setDefaultValue(true);
	}
}