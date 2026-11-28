<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\UI\Presenters\Defaults\AuthPresenter;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenter;
use Nette\Application\PresenterFactory;

/**
 * Presenter projektu (podle mapování) má přednost; když neexistuje, použije se výchozí
 * presenter balíčku pro dané jméno (FancyAdminExtension::DEFAULT_PRESENTERS). Projekt tak píše
 * jen presentery, které mění, a mapování modulů v neonu se nemění.
 */
class FancyAdminPresenterFactory extends PresenterFactory
{
	/** @var array<string, class-string<IPresenter>> 'PortalBackoffice:Accounts' => třída */
	private array $defaultPresenters = [];

	/**
	 * @param array<string, class-string<IPresenter>> $defaultPresenters
	 */
	public function setDefaultPresenters(array $defaultPresenters): static
	{
		$this->defaultPresenters = $defaultPresenters;
		return $this;
	}

	public function getPresenterClass(string &$name): string
	{
		try {
			return parent::getPresenterClass($name);
		} catch (InvalidPresenterException $e) {
			if (isset($this->defaultPresenters[$name]) && !class_exists($this->formatPresenterClass($name))) {
				return $this->defaultPresenters[$name];
			}
			throw $e;
		}
	}

	public function createPresenter(string $name): IPresenter
	{
		$presenter = parent::createPresenter($name);
		if ($presenter instanceof AuthPresenter) {
			$presenter->setProjectPresenterClass($this->formatPresenterClass($name));
		}
		return $presenter;
	}
}
