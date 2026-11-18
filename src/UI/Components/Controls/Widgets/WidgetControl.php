<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;

/**
 * Vykreslení jednoho widgetu. Podkomponentou je proto, aby šel překreslit sám (snippet `body`) - dynamické
 * snippety ve `{snippetArea}` by Nette při překreslení poslalo všechny a data všech widgetů by se načetla znovu.
 */
final class WidgetControl extends Control
{
	#[Persistent]
	public ?string $period = null;

	public function __construct(
		private readonly Widget $widget,
	) {
	}

	public function getWidget(): Widget
	{
		return $this->widget;
	}

	/** @param array<string, mixed> $params */
	public function loadState(array $params): void
	{
		parent::loadState($params);

		$this->widget->selectPeriod($this->period);

		if (!empty($params['redraw']) && $this->getPresenter()->isAjax()) {
			$this->redrawControl('body');
		}
	}

	/** @param array<string, mixed> $params */
	public function saveState(array &$params): void
	{
		if (!array_key_exists('period', $params)) {
			// Výchozí nebo neplatné období do odkazů nepatří
			$period = $this->widget->getPeriod();
			$params['period'] = $period !== $this->widget->getDefaultPeriod() ? $period?->value : null;
		}
		parent::saveState($params);
	}

	/** Jméno pole s obdobím v URL (`dashboard-sales-period` pro komponentu `dashboard`). */
	public function getPeriodParameterName(): string
	{
		return $this->getParameterId('period');
	}

	/** Parametr, kterým JS řekne, že se má překreslit jen tento widget. */
	public function getRedrawParameterName(): string
	{
		return $this->getParameterId('redraw');
	}

	/**
	 * Skrytá pole formuláře výběru období: GET formulář zahodí query z `action`, bez nich by se při
	 * odeslání bez JS ztratily parametry stránky a období ostatních widgetů.
	 *
	 * @return array<string, string>
	 */
	public function getPeriodFormFields(): array
	{
		$fields = [];
		foreach (explode('&', $this->getPresenter()->getHttpRequest()->getUrl()->getQuery()) as $_pair) {
			if ($_pair === '') {
				continue;
			}
			[$_name, $_value] = array_map('urldecode', explode('=', $_pair, 2) + [1 => '']);
			if ($_name !== $this->getPeriodParameterName() && !str_ends_with($_name, '-redraw')) {
				$fields[$_name] = $_value;
			}
		}
		return $fields;
	}

	public function getPeriodFormAction(): string
	{
		return $this->getPresenter()->getHttpRequest()->getUrl()->getPath();
	}

	public function render(): void
	{
		$this->template->widget = $this->widget;
		$this->template->widgetGrid = $this->getParent();
		$this->template->format = WidgetFormat::class;
		$this->template->setFile(__DIR__ . '/WidgetControl.latte');
		$this->template->render();
	}
}
