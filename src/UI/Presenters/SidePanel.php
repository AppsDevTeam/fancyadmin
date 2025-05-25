<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\DoctrineComponents\QueryObject;
use ADT\FancyAdmin\Model\Entities\IEntity;
use ADT\FancyAdmin\Model\Queries\Base\BaseQuery;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Forms\BaseForm;
use Nette\Application\AbortException;
use Nette\Application\UI\Presenter;
use ReflectionException;

trait SidePanel
{
	abstract protected function getEntity(): IEntity|callable|null;
	abstract protected function getForm(): BaseForm;
	abstract protected function getQueryObject(): QueryObject;
	abstract protected function getPresenter(): Presenter;
	abstract public function getSnippetId(string $name): string;

	/**
	 * @throws ReflectionException
	 */
	public function createComponentSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		$entity = $this->getEntity();
		if ($editId = $this->getPresenter()->getParameter('editId')) {
			$entity = $this->getQueryObject()->byId($editId)->fetchOne();
		}

		return $factory->create()
			->setFormFactory(fn() => $this->getForm()->setEntity($entity));
	}
}
