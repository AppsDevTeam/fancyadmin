<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use ADT\AiChat\AgentProvisioner;
use ADT\DoctrineAnonymization\ReadOnlyQueryExecutor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Založí (nebo aktualizuje) Managed Agents agenta a prostředí pro AI asistenta.
 *
 * V deployi není potřeba - agenta založí nebo aktualizuje první konverzace, které
 * chybí nebo má zastaralý systémový prompt či nástroje (AgentProvisioner::resolver).
 * Příkaz je pro ruční zásah: ověření klíče API, vynucená aktualizace agenta. Vždy
 * agentovi pošle aktuální prompt a nástroje, i když se nezměnily.
 *
 * Volá Anthropic API, proto ho testy ani lokální init nespouštějí samy.
 */
#[AsCommand(name: 'fancyadmin:ai:provision-agent', description: 'Založí nebo aktualizuje Managed Agents agenta AI asistenta')]
class ProvisionAiAgentCommand extends Command
{
	public function __construct(
		private readonly AgentProvisioner $provisioner,
		private readonly ReadOnlyQueryExecutor $queryExecutor,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Anonymizované schéma (výchozí podle ai.readOnlyConnection, tj. "<db>_anon")');
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$schema = (string) ($input->getOption('schema') ?? $this->queryExecutor->getSchemaName());

		try {
			[$agent, $created] = $this->provisioner->ensureProvisioned($schema);
		} catch (Throwable $e) {
			$io->error('Agenta pro schéma "' . $schema . '" se nepodařilo zajistit: ' . $e->getMessage());

			return self::FAILURE;
		}

		$io->success(sprintf(
			'%s agent "%s" (prostředí "%s") pro schéma "%s".',
			$created ? 'Založen' : 'Aktualizován',
			$agent->getAgentId(),
			$agent->getEnvironmentId(),
			$schema,
		));

		return self::SUCCESS;
	}
}
