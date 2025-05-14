<?php

namespace ADT\FancyAdmin\UI\Forms\Base;

use ADT\DoctrineForms\Form;
use ADT\Utils\Translatable\TranslatableControlTrait;
use Nette\Forms\Controls;
use Stringable;

/**
 * @property Form $form
 */
class EntityForm extends Form
{
	use TranslatableControlTrait;

	public function addMultiSelect(string $name, Stringable|string|null $label = null, ?array $items = null, ?int $size = null,): Controls\MultiSelectBox
	{
		return parent::addMultiSelect($name, $label, $items, $size)
			->setHtmlAttribute('data-adt-select2', [
				'dropdownCssClass' => 'select2-multiselect-dropdown',
			]);
	}
}
