<?php

declare(strict_types=1);

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Audit\ChangeLogAuditSubscriber;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditedEntity;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestConnection;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestNotAuditedEntity;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * ChangeLogAuditSubscriber - zmena entity (change_log) -> auditni zaznam (audit_log).
 *
 * Do auditu jde jen entita s atributem #[Audited]; change_log je proti tomu husty
 * a patri do nej provozni historie vseho. Hodnoty se prenasi jen u vlastnosti
 * s #[AuditedValue], zbytek zustava jmenem - detail je v change_logu.
 */

require __DIR__ . '/bootstrap.php';


/** @return array{0: ChangeLogAuditSubscriber, 1: TestConnection} */
function createSubscriber(): array
{
	$em = new TestEntityManager([TestAuditLog::class]);
	$metadata = $em->getClassMetadata(TestAuditLog::class);
	$metadata->setPrimaryTable(['name' => 'audit_log']);

	foreach (['action', 'outcome', 'createdAt', 'correlationId', 'createdById', 'createdByLabel', 'createdBy', 'sourceIp', 'userAgent', 'payload'] as $field) {
		$metadata->mapField([
			'fieldName' => $field,
			'type' => 'string',
			'columnName' => strtolower((string) preg_replace('~([a-z])([A-Z])~', '$1_$2', $field)),
		]);
	}

	$actor = new AuditActor(
		new TestSecurityUser(isLoggedIn: false),
		new Request(new UrlScript('https://admin.example.com/app/', '/app/'), remoteAddress: '192.0.2.10'),
	);

	return [
		new ChangeLogAuditSubscriber(new AuditLogger($em), $actor, new SensitiveDataSanitizer()),
		$em->getConnection(),
	];
}

function createChangeLog(string $objectClass, ChangeSet $changeSet, int $id = 77, ?int $objectId = 42): ChangeLog
{
	$logEntry = new ChangeLog();
	$logEntry->setObjectClass($objectClass);
	$logEntry->setObjectId($objectId);
	$logEntry->setAction($changeSet->getAction());
	$logEntry->setChangeSet($changeSet);

	// id prideluje databaze, tady ho dosadime reflexi - subscriber ho dava
	// do correlationId i do payloadu, takze na nem stoji polovina testu
	new ReflectionProperty(ChangeLog::class, 'id')->setValue($logEntry, $id);

	return $logEntry;
}

function createChangeSet(array $properties, string $action = ChangeSet::ACTION_EDIT): ChangeSet
{
	$changeSet = new ChangeSet();
	$changeSet->setAction($action);
	$changeSet->setIdentification(new Id('42', TestAuditedEntity::class, ['email' => 'jan@example.com']));

	foreach ($properties as $property) {
		$changeSet->addPropertyChange($property);
	}

	return $changeSet;
}


test('entita bez atributu Audited se do auditu nezapise', function () {
	// Do change_logu jde vsechno s #[LoggableEntity]; audit je uzsi vyber.
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('title', 'Pivo', 'Pivo 12')]);
	$subscriber->logEntry(createChangeLog(TestNotAuditedEntity::class, $changeSet), new TestNotAuditedEntity(), false);

	Assert::same([], $connection->inserts);
});


test('akce zaznamu je ta z atributu, vysledek uspech', function () {
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('email', 'jan@example.com', 'jan.novak@example.com')]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$data = $connection->getLastInsert()['data'];
	Assert::same('identity_change', $data['action']);
	Assert::same(AuditLogger::OUTCOME_SUCCESS, $data['outcome']);
	// zapis uz probehl, jinak by se zmena neohlasila
	Assert::same('77', $data['correlation_id']);
});


test('payload nese entitu, radek change_logu a nazvy zmenenych vlastnosti', function () {
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([
		new Scalar('email', 'jan@example.com', 'jan.novak@example.com'),
		new Scalar('phoneNumber', '+420123456789', '+420987654321'),
	]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$payload = $connection->getLastInsert()['data']['payload'];
	Assert::same(TestAuditedEntity::class, $payload['entity']);
	Assert::same(42, $payload['entityId']);
	Assert::same(77, $payload['changeLogId']);
	Assert::same('edit', $payload['change']);
	Assert::same(['email', 'phoneNumber'], $payload['properties']);
	Assert::same(['email' => 'jan@example.com'], $payload['identification']);
});


test('hodnoty jen u vlastnosti s AuditedValue', function () {
	// phoneNumber atribut nema: v auditu zustane jmenem, hodnota je v change_logu.
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([
		new Scalar('email', 'jan@example.com', 'jan.novak@example.com'),
		new Scalar('phoneNumber', '+420123456789', '+420987654321'),
	]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$values = $connection->getLastInsert()['data']['payload']['values'];
	Assert::same(['email' => ['old' => 'jan@example.com', 'new' => 'jan.novak@example.com']], $values);
});


test('AuditedValue na privatni vlastnosti rodice se najde', function () {
	// getProperties() vidi jen vlastni privatni vlastnosti, entity dedi pres bazove tridy.
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('state', 'active', 'blocked')]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$values = $connection->getLastInsert()['data']['payload']['values'];
	Assert::same(['state' => ['old' => 'active', 'new' => 'blocked']], $values);
});


test('vazba nese id i identifikaci, kolekce pridane a odebrane', function () {
	[$subscriber, $connection] = createSubscriber();

	$role = new ToOne(
		'role',
		new Id('1', 'App\Entity\AclRole', ['name' => 'Ucetni']),
		new Id('2', 'App\Entity\AclRole', ['name' => 'Administrator']),
	);
	$roles = new ToMany('roles');
	$roles->addAdded(new Id('3', 'App\Entity\AclRole', ['name' => 'Spravce']));
	$roles->addRemoved(new Id('4', 'App\Entity\AclRole', ['name' => 'Ctenar']));

	$subscriber->logEntry(
		createChangeLog(TestAuditedEntity::class, createChangeSet([$role, $roles])),
		new TestAuditedEntity(),
		false,
	);

	$values = $connection->getLastInsert()['data']['payload']['values'];
	Assert::same(['id' => '1', 'identification' => ['name' => 'Ucetni']], $values['role']['old']);
	Assert::same(['id' => '2', 'identification' => ['name' => 'Administrator']], $values['role']['new']);
	Assert::same([['id' => '3', 'identification' => ['name' => 'Spravce']]], $values['roles']['added']);
	Assert::same([['id' => '4', 'identification' => ['name' => 'Ctenar']]], $values['roles']['removed']);
});


test('datum a enum se do JSON sloupce ulozi citelne', function () {
	[$subscriber, $connection] = createSubscriber();

	$validFrom = new DateTimeImmutable('2026-03-01 12:00:00', new DateTimeZone('UTC'));
	$changeSet = createChangeSet([new Scalar('email', null, $validFrom)]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$values = $connection->getLastInsert()['data']['payload']['values'];
	Assert::same($validFrom->format('c'), $values['email']['new']);
});


test('opakovane ohlaseni tehoz radku je poznat', function () {
	// Jedna entita ma v ramci requestu jeden radek change_logu, ktery dalsi flush
	// doplni. Zaznam se zapise znovu cely a priznakem rekne, ze ten predchozi nahrazuje.
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('email', 'jan@example.com', 'jan.novak@example.com')]);
	$logEntry = createChangeLog(TestAuditedEntity::class, $changeSet);
	$entity = new TestAuditedEntity();

	$subscriber->logEntry($logEntry, $entity, false);
	$changeSet->addPropertyChange(new Scalar('state', 'active', 'blocked'));
	$subscriber->logEntry($logEntry, $entity, true);

	Assert::count(2, $connection->inserts);

	$first = $connection->inserts[0]['data']['payload'];
	$second = $connection->inserts[1]['data']['payload'];
	Assert::false(isset($first['supersedesPrevious']));
	Assert::true($second['supersedesPrevious']);
	// stejny radek change_logu, takze i stejny correlation_id - dvojice jde spojit
	Assert::same($connection->inserts[0]['data']['correlation_id'], $connection->inserts[1]['data']['correlation_id']);
	Assert::same(['email', 'state'], $second['properties']);
});


test('citliva hodnota se do auditu nedostane v otevrene podobe', function () {
	// Sanitizer je tentyz jako u ostatnich logu - co je citlive, rozhoduje on.
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('password', 'stare-heslo', 'nove-heslo')]);
	$changeSet->addPropertyChange(new Scalar('email', 'jan@example.com', 'jan.novak@example.com'));
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$payload = $connection->getLastInsert()['data']['payload'];
	Assert::contains('password', $payload['properties']);
	Assert::notContains('stare-heslo', json_encode($payload, JSON_THROW_ON_ERROR));
});


test('akter se vezme z prihlaseneho uzivatele, jinak zustane prazdny', function () {
	[$subscriber, $connection] = createSubscriber();

	$changeSet = createChangeSet([new Scalar('email', 'jan@example.com', 'jan.novak@example.com')]);
	$subscriber->logEntry(createChangeLog(TestAuditedEntity::class, $changeSet), new TestAuditedEntity(), false);

	$data = $connection->getLastInsert()['data'];
	// CLI a cron aktera nemaji, to je platny stav
	Assert::null($data['created_by_id']);
	Assert::same('192.0.2.10', $data['source_ip']);
});
