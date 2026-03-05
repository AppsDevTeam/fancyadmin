<?php

namespace ADT\FancyAdmin\Console;

use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Mailer\Mailer;
use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fancyadmin:create-identity', description: 'Create identity')]
class CreateIdentityCommand extends \ADT\FancyAdmin\Console\Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly AclRoleQueryFactory $aclRoleQueryFactory,
		private readonly FancyAdmin $fancyAdmin,
		private readonly Mailer $mailer
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
		$io = new SymfonyStyle($input, $output);
		$identityEntity = $this->em->findEntityClassByInterface(Identity::class);

		$this->validateInput($io, 'Křestní jméno', $firstname);
		$this->validateInput($io, 'Příjmení', $lastname);
		$this->validateInput($io, 'E-mail', $email);
		$this->validateInput($io, 'Telefon', $phoneNumber);

		/** @var AclRole $role */
		$role = $this->aclRoleQueryFactory->create()->byIsAdmin(true)->fetchOne(false);

		/** @var Identity $identity */
		$identity = (new $identityEntity);
		$identity
			->setContext($this->fancyAdmin->getContext())
			->setFirstName($firstname)
			->setLastName($lastname)
			->setEmail($email)
			->setPhoneNumber($phoneNumber)
			->addRole($role);

		$this->em->persist($identity);
		$this->em->flush();

		$this->mailer->sendAccountCreationEmail($identity);

		return Command::SUCCESS;
	}

	private function validateInput(SymfonyStyle $io, string $question, ?string &$variable): void
	{
		while (!$variable) {
			$variable = trim((string)$io->ask($question));

			if ($variable === '') {
				$io->warning(sprintf('Položka \'%s\' musí být vyplněna', $question));
				$variable = null;
			}
		}
	}
}