<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait ApiKeyTrait
{
	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	#[AuditedValue]
	protected string $name;

	/**
	 * SHA-256 otisk klíče; samotný klíč se v čitelné podobě nikam neukládá.
	 *
	 * Loguje se ZMĚNA, ne hodnota: otisk je materiál k ověření klíče, takže do logu
	 * nepatří ani jako historie. Že se klíč přegeneroval, je naopak zásadní.
	 */
	#[ORM\Column(name: '`key`', unique: true, nullable: true)]
	#[LoggableProperty(withValue: false)]
	protected ?string $key = null;

	/** Převod klíče pod jiný účet mění, k čím datům se jím dá dostat. */
	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[ORM\JoinColumn(nullable: true)]
	#[LoggableProperty]
	#[AuditedValue]
	protected ?Account $account = null;

	/**
	 * Pomocný sloupec, který drží jedinečnost jména v rámci účtu. Nikdo ho nečte
	 * ani nenastavuje, počítá si ho databáze.
	 *
	 * Index přes (name, account_id) by na to nestačil: MySQL bere v unikátním indexu
	 * každý NULL jako jinou hodnotu, takže klíče BEZ účtu - tedy ty globální, které
	 * vidí všichni - by se mohly jmenovat stejně kolikrát chtějí. IFNULL sloučí
	 * globální klíče do jedné skupiny (0) a index pak platí i pro ně.
	 *
	 * Vynutit to jde jenom tady. Kontrola v aplikaci má závod: dva souběžné požadavky
	 * projdou oba, protože ani jeden ještě nevidí zápis toho druhého. Aplikační kontrola
	 * má smysl kvůli hlášce uživateli, ne jako záruka.
	 *
	 * POZOR: samotný unikátní index musí deklarovat entita v projektu, atribut třídy
	 * z traity nepřijde:
	 *
	 *   #[ORM\UniqueConstraint(name: 'uniq_api_key_name_account', columns: ['name', 'account_key'])]
	 *
	 * Definice sloupce MUSÍ končit NOT NULL a mapování ho nesmí mít nullable. Komparátor
	 * columnDefinition nečte, porovnává vlastnosti - a kdyby si mapování a databáze
	 * v nullabilitě odporovaly, padal by rozdíl do každého dalšího migrations-diff.
	 * Na diff, který pořád něco hlásí, si člověk zvykne a přestane ho číst.
	 */
	// Bez generated: 'ALWAYS'. S ním Doctrine po INSERTu hodnotu do entity doplní, ale
	// v původních datech nechá null - a od té chvíle vidí při každém dalším výpočtu změnu,
	// kterou nemá jak zapsat. Takhle zůstane vlastnost u nové entity null (nikdo ji nečte)
	// a u načtené nese, co spočítala databáze.
	#[ORM\Column(
		type: Types::BIGINT,
		insertable: false,
		updatable: false,
		columnDefinition: 'BIGINT GENERATED ALWAYS AS (IFNULL(account_id, 0)) STORED NOT NULL',
	)]
	protected ?string $accountKey = null;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getKey(): ?string
	{
		return $this->key;
	}

	public function setKey(?string $key): static
	{
		$this->key = $key;
		return $this;
	}

	public function getAccount(): ?Account
	{
		return $this->account;
	}

	public function setAccount(?Account $account): static
	{
		$this->account = $account;
		return $this;
	}
}
