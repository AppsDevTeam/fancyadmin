<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\DoctrineForms\BaseForm;
use ADT\FancyAdmin\Model\Entities\IEntity;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelSize;
use Nette\Application\UI\Presenter;
use ReflectionException;

trait SidePanel
{
	abstract protected function getEntity(): IEntity|callable|null;
	abstract protected function getForm(): BaseForm;
	abstract protected function getQueryObject(): QueryObject;
	abstract protected function getPresenter(): ?Presenter;
	abstract public function getSnippetId(string $name): string;
	abstract public function getParameter(string $name): mixed;

	/**
	 * @throws ReflectionException
	 */
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
