<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use Exception;
use Nette\Localization\Translator;
use Nette\Utils\Json;
use stdClass;

trait BasePresenterTrait
{
	use PresenterTrait;
	use FancyAdminInject;
	
	abstract protected function getTranslator(): Translator;

	protected bool $primaryTemplate = false;

	protected function beforeRender(): void
	{
		$this->getTemplate()->primaryTemplate = $this->primaryTemplate;
		$this->getTemplate()->jsComponentsConfig = Json::encode([]);
		$this->getTemplate()->logoFileName = $this->_fancyAdmin->getLogoPublicPath();
		$this->getTemplate()->hmr = $this->_fancyAdmin->getHmr();
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
	 * @return stdClass
	 * @throws Exception
	 */

	public function flashMessage($message, string $type = 'info'): stdClass
	{
		throw new Exception('Use one of flashMessageError / flashMessageWarning / flashMessageSuccess / flashMessageInfo method instead.');
	}

	public function flashMessageError(string $message, ?int $autoCloseDuration = null): stdClass
	{
		return $this->flashMessageCommon($message, 'danger', $autoCloseDuration);
	}

	public function flashMessageWarning(string $message, ?int $autoCloseDuration = null): stdClass
	{
		return $this->flashMessageCommon($message, 'warning', $autoCloseDuration);
	}

	public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null): stdClass
	{
		return $this->flashMessageCommon($message, 'success', $autoCloseDuration);
	}

	public function flashMessageInfo(string $message, ?int $autoCloseDuration = null): stdClass
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
