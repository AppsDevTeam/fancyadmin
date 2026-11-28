<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults;

use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\UI\Presenter;

/**
 * Základ výchozích presenterů portalu bez přihlášení (Sign). Presenter projektu z něj může
 * dědit místo vlastního BasePresenteru.
 */
abstract class BasePresenter extends Presenter implements \ADT\FancyAdmin\UI\Presenters\BasePresenter
{
	use AutowireComponentFactories;
	use AutowireProperties;
	use BasePresenterTrait;
}
