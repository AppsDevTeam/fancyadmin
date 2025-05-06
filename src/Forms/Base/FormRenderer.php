<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Forms\Base;

use ADT\Components\AjaxSelect\AjaxSelect;
use ADT\Forms\BootstrapFormRenderer;
use ADT\Forms\Controls\PhoneNumberInput;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Utils\Html;
use Vodacek\Forms\Controls\DateInput;

class FormRenderer extends BootstrapFormRenderer
{
	protected static ?string $modalHtmlId = null;

	public static function sendErrorPayload(Form $form): void
	{
		if ($form->getPresenter()->isAjax()) {
			$form->getPresenter()->payload->hasErrors = true;
		}
		parent::sendErrorPayload($form);
	}

	protected static function bootstrap5(Container $container): void
	{
		parent::bootstrap5($container);
		$container->getForm()->setHtmlAttribute('data-adt-submit-form');
		$renderer = $container->getForm()->getRenderer();

		if (isset($renderer->wrappers)) {
			$renderer->wrappers['error']['container'] = 'div class=js-errors';
			$renderer->wrappers['error']['item'] = 'div class="alert alert-danger js-error"';
			$renderer->wrappers['control']['erroritem'] = 'div class=js-error';
			$renderer->wrappers['pair']['container'] = 'div class=form-group-sm';
		}

		foreach ($container->getControls() as $control) {
			$type = $control->getOption('type');
			if ($type === 'button') {
				if ($control->getCaption()) {
					$control->setCaption(
						Html::el()
							->addHtml(
								Html::el('span')->setAttribute('class', 'js-spinner spinner-border spinner-border-sm d-none mr-1')
							)->addHtml($control->getTranslator() ? $control->getTranslator()->translate($control->getCaption()) : $control->getCaption())
					);
				}
			} elseif ($control instanceof PhoneNumberInput) {
				$control->getControlPrototype(PhoneNumberInput::CONTROL_COUNTRY_CODE)->setAttribute('data-adt-select2', true);
			} elseif ($control instanceof AjaxSelect) {
				if (
					self::$modalHtmlId
					&&
					!$control->getControl()->getAttribute('data-ajax-select-options')
				) {
					$control->getControlPrototype()
						->setAttribute('data-ajax-select-options', ['dropdownParent' => '#' . self::$modalHtmlId]);
				}
			} elseif ($control->getOption('type') === 'select') {
				if (!$control->getControl()->getAttribute('data-adt-select2')) {
					$attr = self::$modalHtmlId ? ['dropdownParent' => '#' . self::$modalHtmlId] : true;
					$control->getControlPrototype()
						->setAttribute('data-adt-select2', $attr);
				}

				if (count($control->getItems()) < 15) {
					$control->getControlPrototype()
						->setAttribute('data-minimum-results-for-search', -1);
				}
			} elseif ($control instanceof DateInput) {
				$control->getControlPrototype()->setAttribute('autocomplete', 'off');
//				$control->getControlPrototype()->setAttribute('data-adt-date-input', json_encode(['format' => DateInput::$formats[$control->getControlPrototype()->type]]));
			}
		}
	}

	public function setModalHtmlId(?string $htmlId): static
	{
		self::$modalHtmlId = $htmlId;
		return $this;
	}
}
