<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use ADT\FancyAdmin\UI\Presenters\AuthPresenter;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Loaders\RobotLoader;
use Nette\Security\Resource;
use ReflectionClass;
use ReflectionEnum;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fancyadmin:generate-missing-acl-resources', description: 'Generate migration for missing ACL resources')]
class GenerateMissingAclResourcesCommand extends \ADT\FancyAdmin\Console\Command
{
	/**
	 * @param ?DependencyFactory $migrations konfigurace Doctrine Migrations, pokud ji projekt ma -
	 *        z ni se bere, kam migraci zapsat. Balicek na doctrine/migrations nezavisi, proto
	 *        je volitelna; zapojuje ji FancyAdminExtension::beforeCompile().
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly string $appDir,
		private readonly ?DependencyFactory $migrations = null,
	) {
		parent::__construct();
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$loader = new RobotLoader();
		$loader->addDirectory($this->appDir);
		$loader->addDirectory(__DIR__ . '/../Model/Entities/Enums');
		$loader->rebuild();
		$classes = array_keys($loader->getIndexedClasses());

		$requiredResources = array_unique(array_merge(
			$this->findPresenterResources($classes),
			$this->findEnumResources($classes),
		));
		sort($requiredResources);

		$existingResources = $this->getExistingResources();

		$missing = array_diff($requiredResources, $existingResources);

		if (empty($missing)) {
			$io->success('All ACL resources are already present in the database.');
			return Command::SUCCESS;
		}

		$io->info(sprintf('Found %d missing ACL resources:', count($missing)));
		foreach ($missing as $resource) {
			$io->writeln('  - ' . $resource);
		}

		$migrationPath = $this->generateMigration($missing);

		$io->success(sprintf('Migration generated: %s', $migrationPath));

		return Command::SUCCESS;
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	private function findPresenterResources(array $classes): array
	{
		$resources = [];

		foreach ($classes as $class) {
			if (!class_exists($class)) {
				continue;
			}

			$reflection = new ReflectionClass($class);
			if ($reflection->isAbstract()) {
				continue;
			}
			if (!$reflection->implementsInterface(AuthPresenter::class)) {
				continue;
			}

			$resource = $this->resolveResourceName($class);
			if ($resource) {
				$resources[] = $resource;
			}
		}

		return $resources;
	}

	/**
	 * Finds resource names from string-backed enums implementing Nette\Security\Resource.
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	private function findEnumResources(array $classes): array
	{
		$resources = [];

		foreach ($classes as $class) {
			if (!enum_exists($class)) {
				continue;
			}

			$reflection = new ReflectionEnum($class);
			if (!$reflection->implementsInterface(Resource::class)) {
				continue;
			}
			if (!$reflection->isBacked() || (string) $reflection->getBackingType() !== 'string') {
				continue;
			}

			foreach ($class::cases() as $case) {
				$resources[] = $case->value;
			}
		}

		return $resources;
	}

	/**
	 * Derives ACL resource name from class namespace.
	 *
	 * E.g. App\UI\Portal\Backoffice\Presenters\Accounts\AccountsPresenter
	 *   → module parts: [Portal, Backoffice] → PortalBackoffice
	 *   → presenter: Accounts
	 *   → resource: portalBackoffice.accounts
	 *
	 * Moduly zacinaji za segmentem `UI` (adresarova konvence Nette), ne za pevnym poctem
	 * segmentu - projekt nemusi zit pod `App`. Trida bez `UI` se preskoci.
	 */
	private function resolveResourceName(string $class): ?string
	{
		$parts = explode('\\', $class);

		$uiIndex = array_search('UI', $parts, true);
		if ($uiIndex === false) {
			return null;
		}

		// Find the last 'Presenters' segment
		$presentersIndex = array_search('Presenters', array_reverse($parts, true), true);
		if ($presentersIndex === false || $presentersIndex <= $uiIndex) {
			return null;
		}

		// Module: everything between 'UI' and the last 'Presenters', joined
		$moduleParts = array_slice($parts, $uiIndex + 1, $presentersIndex - $uiIndex - 1);
		if (empty($moduleParts)) {
			return null;
		}
		$module = implode('', $moduleParts);

		// Presenter parts: everything after 'Presenters'
		$presenterParts = array_slice($parts, $presentersIndex + 1);
		if (count($presenterParts) < 2) {
			return null; // Skip classes not in a subfolder (e.g. BasePresenter)
		}

		$presenterName = $presenterParts[0];

		return lcfirst($module) . '.' . lcfirst($presenterName);
	}

	/**
	 * @return string[]
	 */
	private function getExistingResources(): array
	{
		return $this->em->getConnection()
			->executeQuery('SELECT name FROM acl_resource')
			->fetchFirstColumn();
	}

	/**
	 * @param string[] $missingResources
	 */
	private function generateMigration(array $missingResources): string
	{
		[$namespace, $migrationsDir] = $this->getMigrationsTarget();

		$timestamp = date('YmdHis');
		$className = 'Version' . $timestamp;

		$sqlStatements = '';
		foreach ($missingResources as $resource) {
			$escaped = addslashes($resource);
			$sqlStatements .= "\t\t\$this->addSql(\"INSERT IGNORE INTO acl_resource (name, title) VALUES ('$escaped', '$escaped')\");\n";
		}

		$content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class $className extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add missing ACL resources';
	}

	public function up(Schema \$schema): void
	{
$sqlStatements	}

	public function down(Schema \$schema): void
	{
	}
}

PHP;

		$filePath = $migrationsDir . '/' . $className . '.php';
		file_put_contents($filePath, $content);

		return $filePath;
	}

	/**
	 * Kam migraci zapsat, se bere z konfigurace Doctrine Migrations - ta je zdrojem pravdy
	 * o namespace i adresari, ne odhad podle `App\Migrations`. Pri vice adresarich vyhrava
	 * prvni nakonfigurovany.
	 *
	 * @return array{string, string} namespace a adresar
	 */
	private function getMigrationsTarget(): array
	{
		$directories = $this->migrations?->getConfiguration()->getMigrationDirectories() ?? [];

		if ($directories === []) {
			throw new RuntimeException('Neni nakonfigurovane Doctrine Migrations (napr. nettrine/migrations s `directories`), takze neni kam migraci zapsat.');
		}

		$namespace = (string) array_key_first($directories);

		return [$namespace, $directories[$namespace]];
	}
}
