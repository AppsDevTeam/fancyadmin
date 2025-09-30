<?php

declare(strict_types=1);

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
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;
use Nette\Loaders\RobotLoader;
use ReflectionClass;
use RuntimeException;

class FancyAdminExtension extends CompilerExtension implements TranslationProviderInterface
{
	private array $defaults = [
		'project' => null,
		'projectName' => null,
		'adminHostPath' => null,
		'defaultCustomerRoute' => 'Portal:Customer:Home',
		'defaultBackofficeRoute' => 'Portal:Backoffice:Home',
		'lostPasswordEnabled' => true,
		'logoPublicPath' => null,
		'logoBitmapPublicPath' => null,
		'hmr' => false,
		'customerAclResource' => null,
		'backofficeAclResource' => null
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

		$builder->addDefinition($this->prefix('administration'))
			->setFactory(FancyAdmin::class, [
				'project' => $this->config['project'],
				'projectName' => $this->config['projectName'],
				'adminHostPath' => $this->config['adminHostPath'],
				'logoPublicPath' => $this->config['logoPublicPath'],
				'logoBitmapPublicPath' => $this->config['logoBitmapPublicPath'],
				'lostPasswordEnabled' => $this->config['lostPasswordEnabled'],
				'defaultCustomerRoute' => $this->config['defaultCustomerRoute'],
				'defaultBackofficeRoute' => $this->config['defaultBackofficeRoute'],
				'hmr' => $this->config['hmr'],
				'customerAclResource' => $this->config['customerAclResource'],
				'backofficeAclResource' => $this->config['backofficeAclResource'],
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
		$loader->addDirectory(__DIR__ . '/../../../../../app/Model/Entities');
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