<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI;

use ADT\FancyAdmin\Console\AclResourceSyncCommand;
use ADT\FancyAdmin\Console\CreateIdentityCommand;
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
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;
use Nette\Loaders\RobotLoader;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Security\Resource;
use ReflectionClass;
use RuntimeException;

class FancyAdminExtension extends CompilerExtension implements TranslationProviderInterface
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'project' => Expect::string()->default(null),
			'projectName' => Expect::string()->default(null),
			'adminHostPath' => Expect::string()->default(null),
			'defaultCustomerRoute' => Expect::string()->default('Portal:Customer:Home'),
			'defaultBackofficeRoute' => Expect::string()->default('Portal:Backoffice:Home'),
			'lostPasswordEnabled' => Expect::bool()->default(true),
			'logoPublicPath' => Expect::string()->default(null),
			'logoBitmapPublicPath' => Expect::string()->default(null),
			'hmr' => Expect::bool()->default(false),
			'customerAclResource' => Expect::type(Resource::class)->default(null),
			'backofficeAclResource' => Expect::type(Resource::class)->default(null),
			'fullDataAclResource' => Expect::type(Resource::class)->default(null),
			'locksDir' => Expect::string()->required(),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$this->config = (new Processor)->process($this->getConfigSchema(), $this->config);

		$builder->addFactoryDefinition($this->prefix('sidePanelControlFactory'))
			->setImplement(SidePanelControlFactory::class)
			->getResultDefinition()
			->setFactory(SidePanelControl::class);

		$builder->addDefinition($this->prefix('fancyAdminRouter'))
			->setFactory(FancyAdminRouter::class);

		$builder->addDefinition($this->prefix('administration'))
			->setFactory(FancyAdmin::class, [
				'project' => $this->config->project,
				'projectName' => $this->config->projectName,
				'adminHostPath' => $this->config->adminHostPath,
				'logoPublicPath' => $this->config->logoPublicPath,
				'logoBitmapPublicPath' => $this->config->logoBitmapPublicPath,
				'lostPasswordEnabled' => $this->config->lostPasswordEnabled,
				'defaultCustomerRoute' => $this->config->defaultCustomerRoute,
				'defaultBackofficeRoute' => $this->config->defaultBackofficeRoute,
				'hmr' => $this->config->hmr,
				'customerAclResource' => $this->config->customerAclResource,
				'backofficeAclResource' => $this->config->backofficeAclResource,
				'fullDataAclResource' => $this->config->fullDataAclResource,
			]);

		//$this->validateTraitInterfaceCompliance();

		// command registration

		$defs[] = $builder->addDefinition($this->prefix('createIdentity'))
			->setFactory(CreateIdentityCommand::class)
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('aclResourceSync'))
			->setFactory(AclResourceSyncCommand::class)
			->setAutowired(false);

		foreach ($defs as $_def) {
			$_def->addSetup('setLocksDir', [$this->config->locksDir]);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$baseQuery = $builder->getDefinitionByType(SecurityUser::class);
		$baseQuery->addSetup('setFullDataAclResource', [$this->config->fullDataAclResource]);
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