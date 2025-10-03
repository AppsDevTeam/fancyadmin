<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\ControlTrait;

trait SidePanel
{
	use ControlTrait;

	abstract protected function getEntity(): Entity|callable|null;
	abstract protected function getForm(): BaseFormInterface;
	abstract protected function getQueryObject(): QueryObjectInterface;

	public function createComponentSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		$entity = $this->getEntity();

		if ($editId = $this->getParameter('editId')) {
			$entity = $this->getQueryObject()->byId($editId)->fetchOne();
		}

		return $factory->create()
			->setSize($this->getSidePanelSize())
			->setFormFactory(fn() => $this->getForm()->setEntity($entity));
	}

	protected function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}
}
