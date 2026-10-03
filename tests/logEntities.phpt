<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use Tester\Assert;

/**
 * AuditLogTrait - auditni log. Provozni request log je v adt/request-logger.
 */

require __DIR__ . '/bootstrap.php';


test('auditni zaznam nese, co se stalo a jak to dopadlo', function () {
	$log = new TestAuditLog('auth.login', 'failure');

	Assert::same('auth.login', $log->getAction());
	Assert::same('failure', $log->getOutcome());
});


test('auditni zaznam je bez vyplneni prazdny', function () {
	$log = new TestAuditLog();

	Assert::null($log->getCreatedById());
	Assert::null($log->getCreatedByLabel());
	Assert::null($log->getCreatedBy());
	Assert::null($log->getSourceIp());
	Assert::null($log->getUserAgent());
	Assert::null($log->getCorrelationId());
	Assert::null($log->getPayload());
});


test('auditni zaznam popise aktera i souvislost', function () {
	$log = new TestAuditLog()->fill(
		createdById: '15',
		createdByLabel: 'Jan Novak',
		createdBy: ['email' => 'jan@example.com'],
		sourceIp: '192.0.2.10',
		userAgent: 'Mozilla/5.0',
		correlationId: 'export-42',
		payload: ['rows' => 10],
	);

	Assert::same('15', $log->getCreatedById());
	Assert::same('Jan Novak', $log->getCreatedByLabel());
	Assert::same(['email' => 'jan@example.com'], $log->getCreatedBy());
	Assert::same('192.0.2.10', $log->getSourceIp());
	Assert::same('Mozilla/5.0', $log->getUserAgent());
	Assert::same('export-42', $log->getCorrelationId());
	Assert::same(['rows' => 10], $log->getPayload());
});


test('cas auditniho zaznamu se vraci vzdy v UTC', function () {
	// V databazi je hodnota v UTC, ale Doctrine ji pri hydrataci oznaci lokalni zonou.
	// Getter ji prestitkuje zpet, bez posunu okamziku.
	$log = new TestAuditLog()->setCreatedAt(new DateTimeImmutable('2026-03-01 12:00:00', new DateTimeZone('Europe/Prague')));

	$createdAt = $log->getCreatedAtUtc();

	Assert::same('UTC', $createdAt->getTimezone()->getName());
	Assert::same('2026-03-01 12:00:00', $createdAt->format('Y-m-d H:i:s'));
});


test('cas se nemeni ani pri prechodu na zimni cas', function () {
	// 2:30 nastane v Praze dvakrat - proto je v databazi UTC.
	$log = new TestAuditLog()->setCreatedAt(new DateTimeImmutable('2026-10-25 02:30:00', new DateTimeZone('UTC')));

	Assert::same('2026-10-25 02:30:00 UTC', $log->getCreatedAtUtc()->format('Y-m-d H:i:s T'));
});
