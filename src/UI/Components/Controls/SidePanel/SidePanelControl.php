<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\SidePanel;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\UI\RenderToStringTrait;
use ADT\Forms\BaseForm;
use Exception;
use Nette\Application\UI\Control;
use Stringable;
use Nette\Http\Url;
use Nette\Utils\Html;

class SidePanelControl extends Control
{
	use RenderToStringTrait;

	/** @var callable */
	private $formFactory;

	private SidePanelSize $size = SidePanelSize::Medium;

	private string $closeConfirm = 'fcadmin.sidePanels.control.closeConfirm';

	private bool $stacked = false;

	/** @var callable|null */
	private $resultLabel = null;

	public function render(): void
	{
		$this->template->size = $this->size->value;
		$this->template->stacked = $this->stacked;
		$this->template->closeConfirm = $this->closeConfirm;
		$this->template->setFile(__DIR__ . '/SidePanelControl.latte');
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

		$baseForm->setOnBeforeInitForm(function (Form $form) {
			$url = new Url($this->getPresenter()->getHttpRequest()->getUrl());
			if ($form->getEntity() && !is_callable($form->getEntity())) {
				$url->setQueryParameter('editId', $form->getEntity()->getId());
			}
			$form->setAction((string) $url);
		})
			->setOnSuccess(function (Form $form) use ($baseForm) {
				$this->getPresenter()->flashMessageSuccess('fcadmin.sidePanels.control.formSaved');
				$snippets = $baseForm->getSnippetsToRedraw();
				if ($this->getPresenter()->isAjax() && ($snippets || $this->stacked)) {
					foreach ($snippets as $snippet) {
						$this->getPresenter()->redrawControl($snippet);
					}
					if ($this->stacked) {
						$this->getPresenter()->payload->sidePanelStackedResult = $this->getResult($form);
					}
					$this->getPresenter()->redrawControl($this->getSnippetName());
					$this->getPresenter()->redrawControl('flashes');
				} else {
					$this->getPresenter()->redirect('this');
				}
			});

		return $baseForm;
	}

	public function setSize(SidePanelSize $size): self
	{
		$this->size = $size;
		return $this;
	}

	public function setCloseConfirm(string $closeConfirm): static
	{
		$this->closeConfirm = $closeConfirm;
		return $this;
	}

	public function setStacked(bool $stacked = true): static
	{
		$this->stacked = $stacked;
		return $this;
	}

	public function isStacked(): bool
	{
		return $this->stacked;
	}

	/**
	 * @param string $link odkaz na signál presenteru
	 * @param string|null $title už přeložený popisek tlačítka (title a aria-label)
	 * @param string $icon třídy ikony
	 */
	public static function createStackedButton(string $link, ?string $title = null, string $icon = 'fa-solid fa-plus'): Html
	{
		$button = Html::el('a')
			->href($link)
			->setAttribute('class', 'btn ajax')
			->setAttribute('data-fancyadmin-side-panel-stacked', true)
			->addHtml(Html::el('i')->setAttribute('class', $icon));

		if ($title !== null) {
			$button->setAttribute('title', $title)
				->setAttribute('aria-label', $title);
		}

		return $button;
	}

	public function getSnippetName(): string
	{
		return $this->stacked ? 'sidePanelStacked' : 'sidePanel';
	}

	/**
	 * @param callable(object): string $resultLabel
	 */
	public function setResultLabel(callable $resultLabel): static
	{
		$this->resultLabel = $resultLabel;
		return $this;
	}

	/**
	 * @return array{value: mixed, label: string}|null
	 */
	public function getResult(Form $form): ?array
	{
		$entity = $form->getEntity();
		if (!is_object($entity) || !method_exists($entity, 'getId')) {
			return null;
		}

		$label = match (true) {
			$this->resultLabel !== null => ($this->resultLabel)($entity),
			$entity instanceof Stringable => (string) $entity,
			default => (string) $entity->getId(),
		};

		return ['value' => $entity->getId(), 'label' => $label];
	}
}