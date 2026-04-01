<?php

namespace ADT\FancyAdmin\UI\Presenters\Js;

use Contributte\Application\Response\StringResponse;
use JetBrains\PhpStorm\NoReturn;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Bridges\ApplicationLatte\TemplateFactory;

trait JsPresenterTrait
{
	#[Autowire]
	protected TemplateFactory $templateFactory;

	protected array $firebaseConfig;

	public function setFirebaseConfig(array $firebaseConfig): void
	{
		$this->firebaseConfig = $firebaseConfig;
	}

	#[NoReturn]
	public function actionFirebaseMessagingSw(): void
	{
		$template = $this->templateFactory->createTemplate();
		$template->setFile(__DIR__ . '/firebase-messaging-sw.js.latte');
		$template->firebaseConfig = $this->firebaseConfig;
		$template->renderToString();

		$this->sendResponse(
			new StringResponse($template->renderToString(), 'firebase-messaging-sw.js', 'application/javascript;')
		);
	}
}
