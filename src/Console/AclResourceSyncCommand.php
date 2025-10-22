<?php

namespace ADT\FancyAdmin\Console;

use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Nette\Security\IResource;
use Nette\Security\Resource;
use ReflectionEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fancyadmin:acl-resource:sync', description: 'AclResource sync')]
class AclResourceSyncCommand extends \ADT\FancyAdmin\Console\Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	)
	{
		parent::__construct();
	}

	/**
	 * @throws NonUniqueResultException
	 * @throws Exception
	 */
	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$autoloadPath = __DIR__ . '/../../../vendor/composer/autoload_classmap.php';
		$classMap = require $autoloadPath;

		$foundEnums = [];

		foreach ($classMap as $className => $filePath) {
			// 💡 Kontrola, že soubor opravdu obsahuje enum — bez autoloadu
			if (!file_exists($filePath)) {
				continue;
			}

			$contents = file_get_contents($filePath);
			if ($contents === false) {
				continue;
			}

			// rychlá kontrola, že v souboru je enum (před autoloadem)
			if (!preg_match('/\benum\b/', $contents)) {
				continue;
			}

			if (!enum_exists($className, false)) {
				continue;
			}

			var_dump($className);
			$ref = new ReflectionEnum($className);

			// buď implementuje interface, nebo má getResourceId() (např. z traitu)
			if ($ref->implementsInterface(Resource::class) || $ref->hasMethod('getResourceId')) {
				$foundEnums[] = $className;
			}
		}

		var_dump($foundEnums);

		return Command::SUCCESS;
	}
}