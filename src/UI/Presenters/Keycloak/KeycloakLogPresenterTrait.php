<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Keycloak;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use Nette\Application\Attributes\CrossOrigin;

trait KeycloakLogPresenterTrait
{
	use PresenterTrait;
	use FancyAdminInject;

	protected function startup(): void
	{
		parent::startup();
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			$this->error('Keycloak is not enabled', 404);
		}
	}

	/**
	 * Logout stránka — vykreslí spinner a JS redirectne na Keycloak logout URL.
	 */
	#[CrossOrigin]
	public function actionOut(): void
	{
		$keycloakSession = $this->getSession(KeycloakSessionSection::SECTION_NAME);
		$keycloakLogoutUrl = $keycloakSession->get(KeycloakSessionSection::LOGOUT_URL);

		$this->getUser()->logout(true);

		$keycloakSession->remove(KeycloakSessionSection::LOGOUT_URL);

		if ($keycloakLogoutUrl === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$this->setLayout(false);
		$this->template->keycloakLogoutUrl = $keycloakLogoutUrl;
	}

	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		$list[] = __DIR__ . '/' . $this->view . '.latte';
		return $list;
	}
}
