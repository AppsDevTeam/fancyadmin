<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\Forms\Form;
use ADT\Forms\StaticContainer;

trait IsActiveFormField
{
	public function addIsActiveField(Form|StaticContainer $form): void
	{
		$form->addCheckbox('isActive', 'fcadmin.forms.user.labels.isActive')
			->setDefaultValue(true);
	}
}