<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use ADT\FancyAdmin\Model\Log\LogMover;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ruční odvoz logů do odděleného úložiště - práci dělá LogMover, tohle je jen obal.
 *
 * V provozu se odvoz obvykle pouští z fronty, aby jel po minutách; tenhle příkaz je
 * pro jednorázové spuštění a pro `--dry-run`, kterým se dá zjistit, kolik toho čeká.
 */
#[AsCommand(name: 'fancyadmin:move-logs', description: 'Odveze logy do odděleného úložiště')]
class MoveLogsCommand extends Command
{
	public function __construct(private readonly LogMover $logMover)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen spočítá, co by odvezl');
		$this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Počet řádků na dávku', (string) LogMover::BATCH_SIZE);
		$this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nejvýš tolik řádků na tabulku za běh (0 = bez omezení)', '0');
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$tables = $this->logMover->getTables();

		if (!$tables) {
			$io->warning('Konfigurace `logMover` je prázdná, není co odvážet.');

			return self::SUCCESS;
		}

		if ($input->getOption('dry-run')) {
			$rows = [];
			foreach ($tables as $_entry) {
				$rows[] = [
					$this->logMover->getSourceTable($_entry['entity']),
					$this->logMover->countWaiting($_entry['entity']),
				];
			}

			$io->table(['zdroj', 'k odvozu'], $rows);

			return self::SUCCESS;
		}

		$result = $this->logMover->moveAll(
			max(1, (int) $input->getOption('batch-size')),
			max(0, (int) $input->getOption('limit')),
		);

		$rows = [];
		foreach ($result['moved'] as $_table => $_count) {
			$rows[] = [$_table, $_count];
		}
		foreach ($result['errors'] as $_table => $_error) {
			$io->error($_table . ': ' . $_error->getMessage());
			$rows[] = [$_table, 'CHYBA'];
		}

		$io->table(['zdroj', 'odvezeno'], $rows);

		return $result['errors'] ? self::FAILURE : self::SUCCESS;
	}
}
