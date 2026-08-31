<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping\Column;

/**
 * Sloupce jednotneho auditniho zaznamu.
 *
 * Zaznam je NEMENNY: zadne settery, vse pres konstruktor a po vytvoreni se
 * uz needituje. Bez jedine relace - audit nezavisi na zbytku databaze
 * a mover ho odveze do dlouhodobeho uloziste beze ztraty vyznamu, i kdyby
 * uzivatel v aplikaci zanikl.
 *
 * IDENTITA UDALOSTI je dvojice (zdroj, id). Zdroj neni sloupec: mover vi,
 * ze ktere databaze cte, a stampuje ho pri odvozu.
 *
 * CO je ploche: kdo (akter), kdy, co se stalo (action), odkud (ip, agent)
 * a k cemu to patri (correlationId). Vsechno ostatni je per-typ udalosti
 * a patri do payloadu - jinak by tabulka mela sloupec pro kazdy typ
 * a vetsinu z nich prazdnou.
 *
 * POZOR: Doctrine cte #[Index] jen z entity, na traite ho IGNORUJE. Entita
 * v projektu proto musi indexy deklarovat sama:
 *
 *   #[ORM\Entity]
 *   #[ORM\Index(fields: ['action'])]
 *   #[ORM\Index(fields: ['correlationId'])]
 *   #[ORM\Index(fields: ['createdById'])]
 *   #[ORM\Index(fields: ['createdAt'])]
 *   class AuditLog extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\AuditLog
 *   {
 *       use AuditLogTrait;
 *   }
 */
trait AuditLogTrait
{
	/** co se stalo; hodnoty definuje kazda knihovna sama (viz jeji rozhrani) */
	#[Column]
	protected string $action;

	/**
	 * Jak akce dopadla: 'success' | 'failure'. Plochy sloupec, protoze je to
	 * dimenze napric typy udalosti (PCI DSS 10.2.2 chce indikaci uspechu/
	 * selhani jako prvek zaznamu) - "vypis vsechna selhani" je pak jeden
	 * WHERE bez vyctu nazvu akci. PROC akce selhala, patri do payload.reason.
	 *
	 * Pozor pro detekcni pravidla: outcome popisuje, jak dopadla TA AKCE,
	 * ne jak se citi uzivatel - napr. zabiti podezrele session je 'success'
	 * (ochrana zafungovala). Pravidla klicujte na action, ne na outcome.
	 */
	#[Column]
	protected string $outcome;

	/**
	 * VZDY V UTC, aby sel zaznam korelovat s logy jinych systemu a nebyl pri
	 * prechodu na zimni cas nejednoznacny (2:30 nastane dvakrat).
	 *
	 * Sloupec je bezny DATETIME; UTC zajistuje ZAPIS (format() bere zonu
	 * z objektu). Getter proto vraci hodnotu vzdy s UTC zonou - pri hydrataci
	 * by Doctrine dosadila lokalni zonu aplikace a okamzik by byl o offset
	 * vedle, tise a bez chyby.
	 */
	#[Column]
	protected DateTimeImmutable $createdAt;

	/** spojovaci klic aktera ve zdrojovem systemu - podle nej se joinuje napric logy */
	#[Column(nullable: true)]
	protected ?string $createdById = null;

	/** lidsky citelne oznaceni aktera - aby sel log cist bez znalosti tvaru createdBy */
	#[Column(nullable: true)]
	protected ?string $createdByLabel = null;

	/** cim dalsim projekt aktera identifikuje (jmeno, e-mail, role, ucet...) */
	#[Column(type: 'json', nullable: true)]
	protected ?array $createdBy = null;

	/** odkud pozadavek prisel - ploche, stoji na tom detekcni pravidla */
	#[Column(length: 45, nullable: true)]
	protected ?string $sourceIp = null;

	#[Column(type: 'text', nullable: true)]
	protected ?string $userAgent = null;

	/**
	 * K cemu udalost patri - podle nej se spoji udalosti tehoz pribehu
	 * (napr. zadani exportu a vsechna jeho stazeni). Retezec, aby to nebylo
	 * omezene na ciselna id.
	 */
	#[Column(nullable: true)]
	protected ?string $correlationId = null;

	/** obsah dle action */
	#[Column(type: 'json', nullable: true)]
	protected ?array $payload = null;

	public function getAction(): string
	{
		return $this->action;
	}

	public function getOutcome(): string
	{
		return $this->outcome;
	}

	public function getCreatedAtUtc(): DateTimeImmutable
	{
		// hodnota je v DB v UTC, ale Doctrine ji pri hydrataci oznacila
		// lokalni zonou - prestitkujeme ji zpatky, bez posunu okamziku
		return DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$this->createdAt->format('Y-m-d H:i:s'),
			new DateTimeZone('UTC'),
		);
	}

	public function getCreatedById(): ?string
	{
		return $this->createdById;
	}

	public function getCreatedByLabel(): ?string
	{
		return $this->createdByLabel;
	}

	public function getCreatedBy(): ?array
	{
		return $this->createdBy;
	}

	public function getSourceIp(): ?string
	{
		return $this->sourceIp;
	}

	public function getUserAgent(): ?string
	{
		return $this->userAgent;
	}

	public function getCorrelationId(): ?string
	{
		return $this->correlationId;
	}

	public function getPayload(): ?array
	{
		return $this->payload;
	}
}
