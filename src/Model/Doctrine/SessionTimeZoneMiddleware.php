<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

/**
 * Nastaví spojení časovou zónu aplikace, jakmile se otevře.
 *
 * Kvůli úložišti logů: logy se ukládají v UTC (viz CreatedAtUtc) a sloupec `created_at`
 * je v PostgreSQL TIMESTAMPTZ. Bez tohohle vrací databáze hodnoty s offsetem
 * +00:00, takže by administrace ukazovala UTC - v létě o dvě hodiny zpátky proti tomu, co
 * uživatel čekal. Se zónou aplikace vrátí PostgreSQL týž okamžik s jejím offsetem a gridy
 * zobrazí projektový čas, aniž by o tom kterýkoli z nich musel vědět - včetně těch, které
 * přicházejí odsud (Change log, Přihlašování) a o zóně projektu nic neví.
 *
 * Řeší to databáze, ne PHP, takže je správně i přechod mezi letním a zimním časem: offset
 * se odvodí ke každému záznamu zvlášť podle jeho data.
 *
 * Uložené hodnoty to nemění - jde jen o to, v čem se čtou.
 */
readonly class SessionTimeZoneMiddleware implements Middleware
{
	public function __construct(private string $timeZone)
	{
	}

	public function wrap(Driver $driver): Driver
	{
		return new class ($driver, $this->timeZone) extends AbstractDriverMiddleware {
			public function __construct(Driver $driver, private readonly string $timeZone)
			{
				parent::__construct($driver);
			}

			public function connect(#[SensitiveParameter] array $params): DriverConnection
			{
				$connection = parent::connect($params);
				// jednoduche uvozeni: jmeno zony je z konfigurace, ne ze vstupu
				$connection->exec("SET TIME ZONE '" . str_replace("'", "''", $this->timeZone) . "'");

				return $connection;
			}
		};
	}
}
