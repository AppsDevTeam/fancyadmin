<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use ADT\FancyAdmin\UI\Presenters\AuthPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Loaders\RobotLoader;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fancyadmin:generate-missing-acl-resources', description: 'Generate migration for missing ACL resources')]
class GenerateMissingAclResourcesCommand extends \ADT\FancyAdmin\Console\Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly string $appDir,
	) {
		parent::__construct();
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$presenterResources = $this->findPresenterResources();
		$existingResources = $this->getExistingResources();

		$missing = array_diff($presenterResources, $existingResources);

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
	 * @return string[]
	 */
	private function findPresenterResources(): array
	{
		$resources = [];

		$loader = new RobotLoader();
		$loader->addDirectory($this->appDir . '/UI');
		$loader->rebuild();

		foreach (array_keys($loader->getIndexedClasses()) as $class) {
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

		$resources = array_unique($resources);
		sort($resources);

		return $resources;
	}

	/**
	 * Derives ACL resource name from class namespace.
	 *
	 * E.g. App\UI\Portal\Backoffice\Presenters\Accounts\AccountsPresenter
	 *   → module parts: [Portal, Backoffice] → PortalBackoffice
	 *   → presenter: Accounts
	 *   → resource: portalBackoffice.accounts
	 */
	private function resolveResourceName(string $class): ?string
	{
		$parts = explode('\\', $class);

		// Find the last 'Presenters' segment
		$presentersIndex = array_search('Presenters', array_reverse($parts, true));
		if ($presentersIndex === false) {
			return null;
		}

		// Module: everything between 'App\UI\' and 'Presenters', joined
		$moduleParts = array_slice($parts, 2, $presentersIndex - 2);
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
		$timestamp = date('YmdHis');
		$className = 'Version' . $timestamp;
		$migrationsDir = $this->appDir . '/../migrations';

		$sqlStatements = '';
		foreach ($missingResources as $resource) {
			$escaped = addslashes($resource);
			$sqlStatements .= "\t\t\$this->addSql(\"INSERT IGNORE INTO acl_resource (name, title) VALUES ('$escaped', '$escaped')\");\n";
		}

		$content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Migrations;

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
}
