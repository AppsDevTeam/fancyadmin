<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestRequestLog;
use ADT\FancyAdmin\Tests\Fixtures\TestRequestLogBody;
use Tester\Assert;

/**
 * AuditLogTrait, RequestLogTrait a RequestLogBodyTrait - auditni a provozni logy.
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


test('zaznam pozadavku nese metodu, URL, kod a IP', function () {
	$log = new TestRequestLog();
	$createdAt = new DateTimeImmutable('2026-03-01 12:00:00.123');

	$log->setCreatedAt($createdAt)
		->setMethod('POST')
		->setUrl('https://admin.example.com/api/orders')
		->setCode(201)
		->setIp('2001:db8::1');

	Assert::same($createdAt, $log->getCreatedAt());
	Assert::same('POST', $log->getMethod());
	Assert::same('https://admin.example.com/api/orders', $log->getUrl());
	Assert::same(201, $log->getCode());
	Assert::same('2001:db8::1', $log->getIp());
});


test('puvodce pozadavku je bud identita, nebo API klic', function () {
	$log = new TestRequestLog();

	Assert::null($log->getIdentityId());
	Assert::null($log->getApiKeyId());
	Assert::null($log->getCorrelationId());

	Assert::same(15, $log->setIdentityId(15)->getIdentityId());
	Assert::same(3, $log->setApiKeyId(3)->getApiKeyId());
	Assert::same('export-42', $log->setCorrelationId('export-42')->getCorrelationId());
});


test('doba odpovedi se uklada na desetiny milisekundy', function () {
	// Scale 4; se scale 2 by byly vsechny pozadavky pod 10 ms nerozlisitelne.
	$log = new TestRequestLog();

	Assert::null($log->getResponseTime());
	Assert::same(0.0123, $log->setResponseTime(0.0123)->getResponseTime());
	Assert::same(0.0123, $log->setResponseTime(0.01234)->getResponseTime());
	Assert::same(0.0124, $log->setResponseTime(0.012356)->getResponseTime());
	Assert::same(12.5, $log->setResponseTime(12.5)->getResponseTime());
	Assert::null($log->setResponseTime(null)->getResponseTime());
});


test('telo pozadavku je cele nepovinne', function () {
	$body = new TestRequestLogBody();

	Assert::null($body->getHeaders());
	Assert::null($body->getParams());
	Assert::null($body->getPostData());
	Assert::null($body->getRawDataJson());
	Assert::null($body->getRawDataText());
	Assert::null($body->getResponseJson());
	Assert::null($body->getResponseText());
});


test('telo pozadavku se navaze na zaznam pozadavku', function () {
	$log = new TestRequestLog();
	$body = new TestRequestLogBody();

	$body->setRequestLog($log)
		->setHeaders(['content-type' => 'application/json'])
		->setParams(['page' => '2'])
		->setPostData('a=1')
		->setRawDataJson(['a' => 1])
		->setRawDataText('{neni json')
		->setResponseJson(['ok' => true])
		->setResponseText('OK');

	Assert::same($log, $body->getRequestLog());
	Assert::same(['content-type' => 'application/json'], $body->getHeaders());
	Assert::same(['page' => '2'], $body->getParams());
	Assert::same('a=1', $body->getPostData());
	Assert::same(['a' => 1], $body->getRawDataJson());
	Assert::same('{neni json', $body->getRawDataText());
	Assert::same(['ok' => true], $body->getResponseJson());
	Assert::same('OK', $body->getResponseText());
});
