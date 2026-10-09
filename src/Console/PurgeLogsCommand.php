<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Maže logovací záznamy starší než jejich retenční doba.
 *
 * Co se maže a jak dlouho se drží, říká konfigurace (viz FancyAdminExtension, klíč
 * `purge`). Retenční doby jsou slib vůči zákazníkovi i regulátorovi, takže mají být
 * na jednom místě, ne rozeseté po commandech.
 *
 * Maže se přes DBAL, ne přes ORM: šlo by o miliony řádků a hydratovat je do entit
 * jen proto, aby se zahodily, by snědlo paměť i čas. Po dávkách s pauzou, protože
 * jedno velké DELETE drží zámky a utíká s ním replika.
 *
 * Auditní stopa (audit_log) do konfigurace NEPATŘÍ - tu odváží a maže mover, až když
 * ji má bezpečně v dlouhodobém úložišti. Smazat ji podle času by znamenalo ztratit
 * záznamy, které ještě nikde jinde nejsou.
 */
#[AsCommand(name: 'fancyadmin:purge-logs', description: 'Smaže logy starší než jejich retenční doba')]
class PurgeLogsCommand extends Command
{
	/** Kompromis mezi dobou držení zámků a počtem dotazů. */
	private const int BATCH_SIZE = 10000;

	/** Pauza mezi dávkami, aby stihla replika i ostatní provoz. */
	private const int SLEEP_MS = 200;

	/**
	 * @param list<array{entity: class-string, retention: string}> $config
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly array $config,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen spočítá, co by smazal');
		$this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Počet řádků na dávku', (string) self::BATCH_SIZE);
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$dryRun = (bool) $input->getOption('dry-run');
		$batchSize = max(1, (int) $input->getOption('batch-size'));

		if (!$this->config) {
			$io->warning('Konfigurace `purge` je prázdná, není co mazat.');

			return self::SUCCESS;
		}

		$rows = [];
		$failed = false;

		foreach ($this->config as $_entry) {
			try {
				$deleted = $this->purge($_entry['entity'], $_entry['retention'], $batchSize, $dryRun);
			} catch (Throwable $e) {
				// jedna rozbitá tabulka nesmí zastavit mazání ostatních - jinak by
				// stačil jeden překlep v konfiguraci a databáze roste dál všude
				$io->error($_entry['entity'] . ': ' . $e->getMessage());
				$rows[] = [$_entry['entity'], $_entry['retention'], 'CHYBA'];
				$failed = true;
				continue;
			}

			$rows[] = [$_entry['entity'], $_entry['retention'], $deleted];
		}

		$io->table(['entita', 'retence', $dryRun ? 'ke smazání' : 'smazáno'], $rows);

		return $failed ? self::FAILURE : self::SUCCESS;
	}

	/**
	 * @param class-string $entityClass
	 * @throws Throwable
	 */
	private function purge(string $entityClass, string $retention, int $batchSize, bool $dryRun): int
	{
		$meta = $this->em->getClassMetadata($entityClass);
		$table = $meta->getTableName();
		$column = $meta->getColumnName('createdAt');

		// hranice se počítá jednou pro celý průchod: kdyby se přepočítávala s každou
		// dávkou, posouvala by se v čase a poslední dávka by mazala jiná data než první
		$threshold = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('-' . $retention);
		if ($threshold === false) {
			throw new \InvalidArgumentException("Retenční doba '$retention' není platný interval.");
		}

		$connection = $this->em->getConnection();
		$thresholdSql = $threshold->format('Y-m-d H:i:s.u');

		if ($dryRun) {
			return (int) $connection->fetchOne(
				"SELECT COUNT(*) FROM $table WHERE $column < ?",
				[$thresholdSql],
			);
		}

		$deleted = 0;
		do {
			$batch = (int) $connection->executeStatement(
				"DELETE FROM $table WHERE $column < ? LIMIT $batchSize",
				[$thresholdSql],
			);
			$deleted += $batch;

			if ($batch > 0) {
				usleep(self::SLEEP_MS * 1000);
			}
		} while ($batch > 0);

		return $deleted;
	}
}
