<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Exception;
use Nette\Application\Attributes\Persistent;
use Nette\Utils\Json;
use stdClass;

trait BasePresenterTrait
{
	use PresenterTrait;
	use FancyAdminInject;
	use EntityManagerInject;
	use TranslatorInject;

	protected bool $primaryTemplate = false;

	#[Persistent]
	public string $backlink = '';
	
	protected function startup(): void
	{
		parent::startup();

		$this->getUser()->onLoggedIn[] = function(SecurityUser $securityUser) {
			if ($onetimeToken = $securityUser->getIdentity()->getOnetimeToken()) {
				$onetimeToken->setUsedAt(new \DateTimeImmutable());
				$this->_em->flush();
			}
		};
	}

	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->getTemplate()->originalTemplate = __DIR__ . '/@layout.latte';
		$this->getTemplate()->primaryTemplate = $this->primaryTemplate;
		$this->getTemplate()->jsComponentsConfig = $this->_fancyAdmin->getJsComponentsConfig();
		$this->getTemplate()->logoFileName = $this->_fancyAdmin->getLogoPublicPath();
		$this->getTemplate()->icon = $this->_fancyAdmin->getLogoMenuPath();
		$this->getTemplate()->loginPageLogoPath = $this->_fancyAdmin->getLoginPageLogoPath();
		$this->getTemplate()->hmr = $this->_fancyAdmin->getHmr();
		$this->getTemplate()->projectName = $this->_fancyAdmin->getProjectName();
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
		//$this->redrawControl('flashes');
		$flash = parent::flashMessage($this->_translator->translate($message), $type);
		$flash->closeDuration = $autoCloseDuration ?? BasePresenter::DEFAULT_AUTO_CLOSE_DURATION;
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
