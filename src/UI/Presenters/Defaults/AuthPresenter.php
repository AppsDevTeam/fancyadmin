<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults;

use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;

/**
 * Základ výchozích presenterů za přihlášením. Presenter projektu z něj může dědit místo
 * vlastního AuthPresenteru.
 *
 * Menu modulu (NavbarMenuFactory, UserMenuFactory) se hledá nejdřív v namespace projektu
 * a teprve pak ve výchozím menu balíčku - menu si projekt upravuje nejčastěji a výchozí
 * presenter by ho jinak hledal ve svém namespace v balíčku.
 */
abstract class AuthPresenter extends BasePresenter implements \ADT\FancyAdmin\UI\Presenters\AuthPresenter
{
	use AuthPresenterTrait {
		getMenuFactoryNamespace as private getMenuFactoryNamespaceByClass;
	}

	/** Třída presenteru projektu podle mapování (nemusí existovat), nastavuje FancyAdminPresenterFactory */
	private ?string $projectPresenterClass = null;

	/** @internal */
	public function setProjectPresenterClass(string $class): void
	{
		$this->projectPresenterClass = $class;
	}

	protected function getMenuFactoryNamespace(): string
	{
		$candidates = [];
		if ($this->projectPresenterClass !== null) {
			$candidates[] = self::presentersNamespace($this->projectPresenterClass);
		}
		if (!str_starts_with(static::class, 'ADT\\FancyAdmin\\')) {
			$candidates[] = $this->getMenuFactoryNamespaceByClass();
		}
		foreach (array_filter($candidates) as $_namespace) {
			if (class_exists($_namespace . '\\NavbarMenuFactory')) {
				return $_namespace;
			}
		}

		$module = explode(':', $this->getName())[0];
		return str_starts_with($this->_fancyAdmin->getDefaultBackofficeRoute(), ':' . $module . ':')
			? __NAMESPACE__ . '\\Backoffice'
			: __NAMESPACE__ . '\\Customer';
	}

	private static function presentersNamespace(string $class): ?string
	{
		$position = strpos($class, '\\Presenters\\');
		return $position === false ? null : substr($class, 0, $position + strlen('\\Presenters'));
	}
}
