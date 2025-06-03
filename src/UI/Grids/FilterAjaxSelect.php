<?php

namespace ADT\FancyAdmin\UI\Grids;

use Nette;
use Ublaboo\DataGrid\Filter\Filter;

class FilterAjaxSelect extends Filter
{
	/**
	 * @var string
	 */
	protected $template = __DIR__ . '/FilterAjaxSelect.latte';

	/**
	 * @var string
	 */
	protected $type = 'ajax_select';

	private $entityName;

	private $prompt;

	/** @var callable|NULL */
	private $onAddToForm = null;


	public function __construct($grid, $key, $name, $entityName, $prompt, $column, $onAddToForm)
	{
		parent::__construct($grid, $key, $name, $column);
		$this->entityName = $entityName;
		$this->prompt = $prompt;
		$this->onAddToForm = $onAddToForm;
	}


	/**
	 * Adds text field to filter form
	 * @param Nette\Forms\Container $container
	 */
	public function addToFormContainer(Nette\Forms\Container $container)
	{
		$component = $container->addAjaxSelect($this->key, $this->name, $this->entityName);
		$component->setPrompt($this->prompt);

		if ($this->onAddToForm) {
			($this->onAddToForm)($component);
		}

		$this->addAttributes($container[$this->key]);
	}


	/**
	 * Return array of conditions to put in result [column1 => value, column2 => value]
	 * 	If more than one column exists in fitler text,
	 * 	than there is OR clause put betweeen their conditions
	 * Or callback in case of custom condition callback
	 * @return array|callable
	 */
	public function getCondition(): array
	{
		return array_fill_keys($this->column, $this->getValue());
	}
}
