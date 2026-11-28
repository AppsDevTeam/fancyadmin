<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\Forms\BootstrapFormRenderer;
use Nette\Forms\Container;

/**
 * Bootstrap renderer s data-adt-submit-form - komponenta Form adt-js-components pak formulář
 * odesílá přes Naja, zamyká ho a chyby vypisuje u prvků.
 */
class SubmitFormRenderer extends BootstrapFormRenderer
{
	protected static function bootstrap5(Container $container): void
	{
		parent::bootstrap5($container);
		$container->getForm()->setHtmlAttribute('data-adt-submit-form');
	}
}
