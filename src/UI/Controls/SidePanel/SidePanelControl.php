<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Controls\SidePanel;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\UI\RenderToStringTrait;
use ADT\Forms\BaseForm;
use Exception;
use Nette\Application\UI\Control;
use Nette\Application\UI\Template;
use Nette\Http\Url;

class SidePanelControl extends Control
{
	use RenderToStringTrait;

	/** @var callable */
	private $formFactory;

	private SidePanelSize $size = SidePanelSize::Medium;

	protected function createTemplate(?string $class = null): Template
	{
		$template = parent::createTemplate();
		$template->size = $this->size->value;
		$template->setFile(__DIR__ . '/SidePanelControl.latte');
		return $template;
	}

	public function render(): void
	{
		$this->template->render();
	}

	public function setFormFactory(callable $formFactory): static
	{
		$this->formFactory = $formFactory;
		return $this;
	}

	/**
	 * @throws Exception
	 */
	protected function createComponentForm(): BaseForm
	{
		/** @var BaseForm $baseForm */
		$baseForm = ($this->formFactory)();

		$this->setSize($baseForm->getSidePanelSize());

		$baseForm->setOnBeforeInitForm(function (Form $form) {
			$url = new Url($this->getPresenter()->getHttpRequest()->getUrl());
			if ($form->getEntity() && !is_callable($form->getEntity())) {
				$url->setQueryParameter('editId', $form->getEntity()->getId());
			}
			$form->setAction((string) $url);
		})
			->setOnSuccess(function (Form $form) use ($baseForm) {
				$this->getPresenter()->flashMessageSuccess($form->getEntity() ? 'app.sidePanels.control.itemEdit' : 'app.sidePanels.control.itemAdd');
				if ($redirect = $baseForm->getRedirect($form->getEntity())) {
					$this->getPresenter()->redirect($redirect[0], array_merge($redirect[1], ['redrawSidePanel' => true]));
				} else {
					$this->getPresenter()->redrawControl('container');
					$this->getPresenter()->redrawControl('sidePanelContainer');
				}
			});

		return $baseForm;
	}

	public function setSize(SidePanelSize $size): self
	{
		$this->size = $size;
		return $this;
	}
}