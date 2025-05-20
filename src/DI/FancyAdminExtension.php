<?php

namespace ADT\FancyAdmin\DI;

use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Services\DeleteService;
use ADT\FancyAdmin\UI\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\Model\Administration;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\Application\LinkGenerator;

class FancyAdminExtension extends \Nette\DI\CompilerExtension implements TranslationProviderInterface
{
	private $defaults = [
		'adminHostPath' => '/admin',
		'homepagePresenter' => '',
		'lostPasswordEnabled' => false,
		'navbarMenuFactory' => NavbarMenuFactory::class,
		'authenticator' => \Nette\Security\Authenticator::class,
		'forms' => [
			'signInFactory' => SignInFormFactory::class
		]
	];

	public function loadConfiguration() {
		$this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		$builder->addFactoryDefinition($this->prefix('signInFormFactory'))
			->setImplement(SignInFormFactory::class)
			->getResultDefinition()
			->setFactory(SignInForm::class)
			->addSetup('setAuthenticator', ['@' . $this->config['authenticator']])
			->addSetup('setAdministration', ['@' . Administration::class]);

		$builder->addFactoryDefinition($this->prefix('newPasswordFormFactory'))
			->setImplement(NewPasswordFormFactory::class);

		$builder->addDefinition($this->prefix('newPasswordForm'))
			->setFactory(NewPasswordForm::class);

		$builder->addFactoryDefinition($this->prefix('lostPasswordFormFactory'))
			->setImplement(LostPasswordFormFactory::class);

		$builder->addDefinition($this->prefix('lostPasswordForm'))
			->setFactory(LostPasswordForm::class);

		$builder->addDefinition($this->prefix('fancyAdminRouter'))
			->setFactory(FancyAdminRouter::class);

		$builder->addDefinition($this->prefix('deleteService'))
			->setFactory(DeleteService::class);

		$builder->addDefinition($this->prefix('administration'))
			->setFactory(Administration::class, [
				'adminHostPath' => $this->config['adminHostPath'],
				'homepagePresenter' => $this->config['homepagePresenter'],
				'lostPasswordEnabled' => $this->config['lostPasswordEnabled'],
				'navbarMenuFactory' => '@' . $this->config['navbarMenuFactory'],
				'linkGenerator' => '@' . LinkGenerator::class,
			]);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}