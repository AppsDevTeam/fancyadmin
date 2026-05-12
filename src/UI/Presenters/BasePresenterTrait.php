<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\JsComponentsInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Exception;
use Nette\Application\Attributes\Persistent;
use Nette\Http\Session;
use Nette\Utils\Json;
use stdClass;

trait BasePresenterTrait
{
	use PresenterTrait;
	use FancyAdminInject;
	use EntityManagerInject;
	use TranslatorInject;
	use JsComponentsInject;

	protected bool $primaryTemplate = false;

	#[Persistent]
	public string $backlink = '';

	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->getTemplate()->originalTemplate = __DIR__ . '/@layout.latte';
		$this->getTemplate()->primaryTemplate = $this->primaryTemplate;
		$this->getTemplate()->logoFileName = $this->_fancyAdmin->getLogoPublicPath();
		$this->getTemplate()->faviconFileNamePng = $this->_fancyAdmin->getFaviconFileNamePng();
		$this->getTemplate()->faviconFileNameSvg = $this->_fancyAdmin->getFaviconFileNameSvg();
		$this->getTemplate()->icon = $this->_fancyAdmin->getLogoMenuPath();
		$this->getTemplate()->loginPageLogoPath = $this->_fancyAdmin->getLoginPageLogoPath();
		$this->getTemplate()->hmr = $this->_fancyAdmin->getHmr();
		$this->getTemplate()->projectName = $this->_fancyAdmin->getProjectName();
		$this->getTemplate()->colors = $this->_fancyAdmin->getColors();
		$this->_jsComponents->setComponents($this->_fancyAdmin->getJsComponentsConfig());
		$this->_jsComponents->setFirebaseLink('setFirebaseTokenLink', $this->getPresenter()->link('setFirebaseToken!', ['firebaseToken' => '__firebaseToken__']));
		$this->_jsComponents->setFirebaseLink('removeFirebaseTokenLink', $this->getPresenter()->link('removeFirebaseToken!', ['firebaseToken' => '__firebaseToken__']));
		$this->_jsComponents->setFirebaseLink('removeAllFirebaseTokensLink', $this->getPresenter()->link('removeAllFirebaseTokens!'));
		$this->getTemplate()->jsComponentsConfig = $this->_jsComponents->generateConfig();

		// Keycloak — dynamický frame-src CSP header pro silent SSO iframe
		if ($this->_fancyAdmin->isKeycloakEnabled()) {
			$ssoClass = $this->_em->findEntityClassByInterface(\ADT\FancyAdmin\Model\Entities\Sso::class);
			$ssoRecords = $this->_em->getRepository($ssoClass)->findAll();
			if (!empty($ssoRecords)) {
				$ssoUrls = [];
				foreach ($ssoRecords as $sso) {
					$ssoUrls[] = $sso->getHostUrl();
				}
				$urls = implode(' ', $ssoUrls);
				$currentCsp = $this->getHttpResponse()->getHeader('Content-Security-Policy') ?? '';

				// Přidáme SSO URL k existujícím connect-src a přidáme frame-src
				if (preg_match('/connect-src\s+([^;]+)/', $currentCsp, $m)) {
					$currentCsp = str_replace($m[0], $m[0] . ' ' . $urls, $currentCsp);
				} else {
					$currentCsp .= '; connect-src \'self\' ' . $urls;
				}
				$currentCsp .= '; frame-src \'self\' ' . $urls;

				$this->getHttpResponse()->setHeader('Content-Security-Policy', $currentCsp);
			}
		}

		// Keycloak settings pro frontend (keycloak-js adapter)
		$keycloakSettingsJson = '';
		if ($this->_fancyAdmin->isKeycloakEnabled()) {
			$isKeycloakUserLoggedIn = $this->getUser()->isLoggedIn()
				&& $this->getPresenter()->getSession(KeycloakSessionSection::SECTION_NAME)->get(KeycloakSessionSection::ID_TOKEN) !== null;

			if ($isKeycloakUserLoggedIn) {
				$keycloak = $this->_fancyAdmin->getKeycloakManager()?->getInstanceFromSession();
				if ($keycloak !== null) {
					$keycloakSettingsJson = Json::encode([
						'realm' => $keycloak->getRealm(),
						'clientId' => $keycloak->getFrontendClientId(),
						'url' => $keycloak->getHostUrl(),
						'silentCheckSsoUrl' => $this->getPresenter()->link('//:Portal:KeycloakAuth:silentCheckSso'),
						'logoutUrl' => $this->getPresenter()->link('//:Portal:Sign:out'),
					]);
				}
			}
		}
		$this->getTemplate()->keycloakSettingsJson = $keycloakSettingsJson;
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
			$this->redrawControl('modals');
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

	public function handleSetFirebaseToken(string $firebaseToken): void
	{
		$this->getUser()->getIdentity()
			->addFirebaseToken($firebaseToken);

		$this->em->flush();

		$this->flashMessageSuccess('fcadmin.firebase.notifications.flashes.success');
	}

	public function handleRemoveFirebaseToken(string $firebaseToken): void
	{
		$this->getUser()->getIdentity()
			->removeFirebaseToken($firebaseToken);

		$this->em->flush();

		$this->flashMessageSuccess('fcadmin.firebase.notifications.flashes.disabled');
	}

	public function handleRemoveAllFirebaseTokens(): void
	{
		$this->getUser()->getIdentity()
			->setFirebaseTokens([]);

		$this->em->flush();

		$this->flashMessageSuccess('fcadmin.firebase.notifications.flashes.disabled');
	}
}
