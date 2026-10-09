<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Audit;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\PropertyChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\FancyAdmin\Model\Attributes\Audited;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;
use ADT\FancyAdmin\Model\Entities;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use ReflectionClass;
use Stringable;
use UnitEnum;

/**
 * Převádí uložené změny entit (change_log) na záznamy v audit_log.
 *
 * Navěšuje se na LoggableListener::$onLogEntry, což adt/doctrine-loggable ohlašuje
 * z postFlush - tedy až po commitu. Do auditu se proto nedostane změna, která se
 * nakonec nezapsala.
 *
 * Knihovna ohlašuje VŠECHNY změny a nefiltruje; výběr je tady. Change_log je provozní
 * historie a smí být hustá, auditní stopa je bezpečnostní záznam s dlouhou retencí -
 * kdyby do ní padalo všechno, utopí se v ní to podstatné a poroste donekonečna.
 *
 * Vybírá se ve dvou krocích: entity fancyadminu podle ROZHRANÍ, které implementují
 * (viz DEFAULT_ACTIONS), cokoliv dalšího podle atributu #[Audited] na entitě. Projekt
 * tak navěšením v neonu rovnou získá audit identit, oprávnění, účtů a konfigurace,
 * aniž by k tomu musel obcházet entity - a atributem si přidá ty svoje.
 *
 * Záznam nese změněné vlastnosti jmény; hodnoty jen u těch s #[AuditedValue].
 * Detail je vždy v change_logu, kam z auditu vede payload.changeLogId.
 */
final class ChangeLogAuditSubscriber
{
	private const string PAYLOAD_KEY_CHANGE_LOG_ID = 'changeLogId';

	/**
	 * Výchozí akce pro entity fancyadminu, klíčované rozhraním - atribut na traitě by
	 * nestačil, ten reflexe na cílové třídě nevidí.
	 *
	 * Osa je DOMÉNA, ne entita: detekční pravidla se pak klíčují na jednu hodnotu místo
	 * výčtu tříd a přibytí další entity do domény nic nerozbije. Hodnoty se prvním
	 * nasazením fixují - audit_log je append-only, zpětně je přejmenovat nejde.
	 *
	 * Pořadí rozhoduje: první rozhraní, které entita implementuje, vyhrává.
	 *
	 * @var array<class-string, string>
	 */
	private const array DEFAULT_ACTIONS = [
		Entities\Identity::class => 'identity_change',
		Entities\Passkey::class => 'identity_change',
		Entities\ApiKey::class => 'identity_change',
		Entities\Sso::class => 'identity_change',
		Entities\Acl::class => 'acl_change',
		Entities\AclRole::class => 'acl_change',
		Entities\AclResource::class => 'acl_change',
		Entities\Profile::class => 'account_change',
		Entities\Account::class => 'account_change',
		Entities\Configuration::class => 'configuration_change',
	];

	/** @var array<class-string, string|null> */
	private array $actions = [];

	/** @var array<class-string, list<string>> */
	private array $auditedProperties = [];

	public function __construct(
		private readonly AuditLogger $auditLogger,
		private readonly AuditActor $auditActor,
		private readonly SensitiveDataSanitizer $sanitizer,
	) {
	}

	/**
	 * @param bool $announced Tentýž řádek change_logu už jednou ohlášen byl a teď se
	 *        jen rozrostl - jedna entita má v rámci requestu jeden řádek, který každý
	 *        další flush doplní. Zahodit opakování nejde, nesly by změny z pozdějších
	 *        flushů; záznam se proto zapíše znovu, celý, s příznakem supersedesPrevious.
	 */
	public function logEntry(ChangeLog $logEntry, object $entity, bool $announced): void
	{
		$action = $this->resolveAction($logEntry->getObjectClass());
		if ($action === null) {
			return;
		}

		$changeSet = $logEntry->getChangeSet();

		$payload = [
			'entity' => $logEntry->getObjectClass(),
			'entityId' => $logEntry->getObjectId(),
			self::PAYLOAD_KEY_CHANGE_LOG_ID => $logEntry->getId(),
			'change' => $changeSet->getAction(),
			'properties' => array_keys($changeSet->getChangedProperties()),
		];

		if ($identification = $changeSet->getIdentification()?->getIdentification()) {
			$payload['identification'] = $identification;
		}

		if ($values = $this->collectValues($logEntry->getObjectClass(), $changeSet)) {
			$payload['values'] = $values;
		}

		// Záznam obsahuje i to, co nesl ten předchozí se stejným changeLogId -
		// změnový set je kumulativní. Čtenář tak pozná, který z dvojice platí.
		if ($announced) {
			$payload['supersedesPrevious'] = true;
		}

		$this->auditLogger->log(
			action: $action,
			// zápis proběhl, jinak by se změna neohlásila; neúspěšný pokus o změnu
			// je zamítnutý přístup a ten loguje AccessDenied stopa, ne tahle
			outcome: AuditLogger::OUTCOME_SUCCESS,
			createdAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
			// řádek change_logu drží detail změny - podle něj se audit a provozní
			// historie spojí, a opakované ohlášení téhož řádku je poznat
			correlationId: (string) $logEntry->getId(),
			actor: $this->auditActor->create(),
			payload: (array) $this->sanitizer->sanitize($payload),
		);
	}

	/**
	 * Hodnoty jen u vlastností s #[AuditedValue] - viz atribut, proč ne u všech.
	 *
	 * @param class-string $entityClass
	 * @return array<string, mixed>
	 */
	private function collectValues(string $entityClass, ChangeSet $changeSet): array
	{
		$auditedProperties = $this->readAuditedProperties($entityClass);
		if (!$auditedProperties) {
			return [];
		}

		$values = [];
		foreach ($changeSet->getChangedProperties() as $name => $property) {
			if (in_array($name, $auditedProperties, true)) {
				$values[$name] = $this->describe($property);
			}
		}

		return $values;
	}

	/** @return array<string, mixed> */
	private function describe(PropertyChangeSet $property): array
	{
		if ($property instanceof Scalar) {
			return ['old' => $this->normalize($property->getOld()), 'new' => $this->normalize($property->getNew())];
		}

		if ($property instanceof ToOne) {
			return ['old' => $this->describeId($property->getOld()), 'new' => $this->describeId($property->getNew())];
		}

		if ($property instanceof ToMany) {
			return [
				'added' => array_map($this->describeId(...), $property->getAdded()),
				'removed' => array_map($this->describeId(...), $property->getRemoved()),
			];
		}

		// vlastní typ změny z novější verze knihovny - radši název typu než tichý výpadek
		return ['type' => $property->getType()];
	}

	/** @return array<string, mixed>|null */
	private function describeId(?Id $id): ?array
	{
		if ($id === null) {
			return null;
		}

		return array_filter([
			'id' => $id->getId(),
			'identification' => $id->getIdentification(),
		], static fn ($value) => $value !== null && $value !== []);
	}

	/**
	 * Hodnota jde do `json` sloupce, takže z ní musí být něco, co JSON unese
	 * a co za rok někdo přečte - ne "[object]" nebo jméno třídy proxy.
	 */
	private function normalize(mixed $value): mixed
	{
		if ($value instanceof DateTimeInterface) {
			// stejný tvar jako jinde v auditu, s offsetem: bez něj je okamžik nejednoznačný
			return $value->format('c');
		}

		if ($value instanceof BackedEnum) {
			return $value->value;
		}

		if ($value instanceof UnitEnum) {
			return $value->name;
		}

		if ($value instanceof Stringable) {
			return (string) $value;
		}

		if (is_object($value)) {
			return $value::class;
		}

		return $value;
	}

	/**
	 * Akce pro tuto entitu, nebo null, když do auditní stopy nepatří.
	 *
	 * Atribut má přednost před výchozí mapou: projekt tak může entitě fancyadminu
	 * akci přepsat, když mu doménové dělení nesedí.
	 *
	 * @param class-string $entityClass
	 */
	private function resolveAction(string $entityClass): ?string
	{
		if (!array_key_exists($entityClass, $this->actions)) {
			$attributes = new ReflectionClass($entityClass)->getAttributes(Audited::class);
			$this->actions[$entityClass] = $attributes
				? $attributes[0]->newInstance()->action
				: $this->defaultAction($entityClass);
		}

		return $this->actions[$entityClass];
	}

	/** @param class-string $entityClass */
	private function defaultAction(string $entityClass): ?string
	{
		foreach (self::DEFAULT_ACTIONS as $interface => $action) {
			if (is_a($entityClass, $interface, true)) {
				return $action;
			}
		}

		return null;
	}

	/**
	 * @param class-string $entityClass
	 * @return list<string>
	 */
	private function readAuditedProperties(string $entityClass): array
	{
		if (!isset($this->auditedProperties[$entityClass])) {
			$names = [];
			// getProperties() nevidí privátní vlastnosti rodičů, a entity je přes
			// traity a bázové třídy běžně dědí - proto celá hierarchie
			for ($class = new ReflectionClass($entityClass); $class !== false; $class = $class->getParentClass()) {
				foreach ($class->getProperties() as $property) {
					if ($property->getAttributes(AuditedValue::class)) {
						$names[$property->getName()] = true;
					}
				}
			}

			$this->auditedProperties[$entityClass] = array_keys($names);
		}

		return $this->auditedProperties[$entityClass];
	}
}
