<?php

namespace ADT\FancyAdmin\UI\Presenters\Manifest;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use Contributte\Application\Response\StringResponse;
use JetBrains\PhpStorm\NoReturn;
use Nette\Utils\Json;

/**
 * Web manifest administrace: nazev a ikona aplikace pridane na plochu / domovskou
 * obrazovku. Odvozuje se z konfigurace projektu (projectName, appIconsDir), takze novy
 * projekt nemusi nic upravovat.
 */
trait ManifestPresenterTrait
{
	use FancyAdminInject;

	#[NoReturn]
	public function actionDefault(): void
	{
		$this->sendResponse(new StringResponse(
			Json::encode($this->_fancyAdmin->createWebManifest(), pretty: true),
			'manifest.webmanifest',
			'application/manifest+json',
		));
	}
}
