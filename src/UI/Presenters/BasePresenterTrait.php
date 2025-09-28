<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineAuthenticator\SecurityUser;
use ADT\FancyAdmin\Model\FancyAdmin;
use Contributte\Translation\Translator;
use Exception;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use ReflectionClass as T;

trait BasePresenterTrait
{
	use PresenterTrait;

	use AutowireProperties;
	use AutowireComponentFactories;
	
	abstract protected function getTranslator(): \Nette\Localization\Translator;

	const DEFAULT_AUTO_CLOSE_DURATION = 3000;


	protected bool $primaryTemplate = false;

	protected FancyAdmin $_fancyAdmin;
	public function injectFancyAdmin(FancyAdmin $fancyAdmin)
	{
		$this->_fancyAdmin = $fancyAdmin;
	}

	protected function beforeRender(): void
	{
		$this->template->primaryTemplate = $this->primaryTemplate;
		$this->template->jsComponentsConfig = Json::encode([]);
		$this->template->logoFileName = $this->_fancyAdmin->getLogoFileName();
	}

	public function handleRedrawBody(): void
	{
		$this->redrawControl('body');
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
		$flash = parent::flashMessage($this->getTranslator()->translate($message), $type);
		$flash->closeDuration = $autoCloseDuration ?? self::DEFAULT_AUTO_CLOSE_DURATION;
		return $flash;
	}

	public function getAdministration(): FancyAdmin
	{
		return $this->administration;
	}

	public function afterRender(): void
	{
		if (!$this->isControlInvalid()) {
			$this->redrawControl('title');
			$this->redrawControl('body');
		}
	}

	/**
	 * Formats view template file names.
	 * @return array
	 */
	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		$list[] = __DIR__ . '/' . explode(':', $this->name)[1] . '/' . $this->view . '.latte';
		return $list;
	}

	public function formatLayoutTemplateFiles(): array
	{
		$list = parent::formatLayoutTemplateFiles();
		$list[] = __DIR__ . "/@layout.latte";
		return $list;
	}
}
