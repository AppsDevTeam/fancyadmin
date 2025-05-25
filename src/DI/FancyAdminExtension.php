<?php

namespace ADT\FancyAdmin\DI;

use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclResourceTrait;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\AclRoleTrait;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Entities\ProfileTrait;
use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Services\DeleteService;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\Model\Administration;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\Application\LinkGenerator;
use Nette\DI\CompilerExtension;
use Nette\Loaders\RobotLoader;
use Nette\Security\Authenticator;
use ReflectionClass;
use RuntimeException;

class FancyAdminExtension extends CompilerExtension implements TranslationProviderInterface
{
	private array $defaults = [
		'adminHostPath' => '/admin',
		'homepagePresenter' => 'Home:default',
		'lostPasswordEnabled' => true,
		'navbarMenuFactory' => NavbarMenuFactory::class,
		'authenticator' => Authenticator::class,
	];

	public function loadConfiguration(): void
	{
		$this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		$builder->addFactoryDefinition($this->prefix('sidePanelControlFactory'))
			->setImplement(SidePanelControlFactory::class)
			->getResultDefinition()
			->setFactory(SidePanelControl::class);

		$builder->addDefinition($this->prefix('fancyAdminRouter'))
			->setFactory(FancyAdminRouter::class);

		$builder->addFactoryDefinition($this->prefix('navbarMenuFactory'))
			->setImplement($this->config['navbarMenuFactory'])
			->getResultDefinition()
			->setFactory(NavbarMenu::class);

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

		$this->validateTraitInterfaceCompliance();
	}

	private function validateTraitInterfaceCompliance(): void
	{
		$traitInterfaceMap = [
			AclResourceTrait::class => AclResource::class,
			AclRoleTrait::class => AclRole::class,
			IdentityTrait::class => Identity::class,
			ProfileTrait::class => Profile::class,
		];

		$loader = new RobotLoader();
		$loader->addDirectory(__DIR__ . '/../../../../../app/model/Entities');
		$loader->acceptFiles = ['*.php'];
		$loader->rebuild();

		foreach (array_keys($loader->getIndexedClasses()) as $class) {
			if (!class_exists($class)) {
				continue;
			}

			$reflection = new ReflectionClass($class);

			if (!$reflection->isInstantiable() || $reflection->isAbstract()) {
				continue;
			}

			$usedTraits = $this->class_uses_recursive($class);

			foreach ($traitInterfaceMap as $trait => $interface) {
				if (in_array($trait, $usedTraits, true) && !$reflection->implementsInterface($interface)) {
					throw new RuntimeException("Třída $class používá $trait, ale neimplementuje požadované rozhraní $interface.");
				}
			}
		}
	}

	private function class_uses_recursive(string $class): array
	{
		$results = [];

		do {
			$results += class_uses($class);
		} while ($class = get_parent_class($class));

		foreach ($results as $trait) {
			$results += $this->class_uses_recursive($trait);
		}

		return array_unique($results);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}