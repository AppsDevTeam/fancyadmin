<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineAuthenticator\SecurityUser;
use ADT\FancyAdmin\Model\Administration;
use Contributte\Translation\Translator;
use Exception;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use ReflectionClass as T;

abstract class BasePresenter extends Presenter
{
	use AutowireProperties;
	use AutowireComponentFactories;

	const DEFAULT_AUTO_CLOSE_DURATION = 3000;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected SecurityUser $securityUser;

	#[Autowire]
	protected Administration $administration;

	protected bool $primaryTemplate = false;

	public array $allowedMethods = ['GET', 'POST', 'HEAD'];

	public function isLogged(): bool
	{
		return $this->getUser()->isLoggedIn();
	}

	protected function beforeRender(): void
	{
		$this->template->primaryTemplate = $this->primaryTemplate;
		$this->template->jsComponentsConfig = Json::encode([]);
	}

	/**
	 * @template T of \ReflectionClass|\ReflectionMethod
	 * @param T $element
	 * @return void
	 */
	public function checkRequirements(\ReflectionClass|\ReflectionMethod $element): void
	{
		parent::checkRequirements($element);

		if (!method_exists($this, 'action' . ucfirst($this->getAction()))) {
			$this->error();
		}
	}


	/************************
	 **** FLASH MESSAGES ****
	 ***********************
	 * @param string $message
	 * @param string $type
	 * @return \stdClass
	 */

	public function flashMessage($message, string $type = 'info'): \stdClass
	{
		throw new Exception('Use one of flashMessageError / flashMessageWarning / flashMessageSuccess / flashMessageInfo method instead.');
	}

	public function flashMessageError(string $message, ?int $autoCloseDuration = null): \stdClass
	{
		return $this->flashMessageCommon($message, 'danger', $autoCloseDuration);
	}

	public function flashMessageWarning(string $message, ?int $autoCloseDuration = null): \stdClass
	{
		return $this->flashMessageCommon($message, 'warning', $autoCloseDuration);
	}

	public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null): \stdClass
	{
		return $this->flashMessageCommon($message, 'success', $autoCloseDuration);
	}

	public function flashMessageInfo(string $message, ?int $autoCloseDuration = null): \stdClass
	{
		return $this->flashMessageCommon($message, 'info', $autoCloseDuration);
	}

	/** @internal */
	private function flashMessageCommon(string $message, string $type, ?int $autoCloseDuration = null)
	{
		$this->redrawControl('flashes');
		$flash = parent::flashMessage($this->translator->translate($message), $type);
		$flash->closeDuration = $autoCloseDuration ?? self::DEFAULT_AUTO_CLOSE_DURATION;
		return $flash;
	}

	/**
	 * Formats layout template file names.
	 * @return array
	 */
	public function formatLayoutTemplateFiles(): array
	{
		$list = parent::formatLayoutTemplateFiles();
		$dir = dirname($this->getReflection()->getFileName());
		$list[] = "$dir/../@layout.Latte";
		$list[] = __DIR__ . "/@layout.latte";
		return $list;
	}

	/**
	 * Formats view template file names.
	 * @return array
	 */
	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		$dir = dirname($this->getReflection()->getFileName());
		$list[] = "$dir/$this->view.Latte";
		return $list;
	}

	public function getAdministration(): Administration
	{
		return $this->administration;
	}
}
