<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAtUtc;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAtUtc;
use Tester\Assert;

/**
 * Casova zona logu.
 *
 * Logy se ukladaji v UTC, protoze koncí v jednom ulozisti vedle sebe a mover jim pri odvozu
 * pripisuje `+00:00` - lokalni cas by tam skoncil posunuty o offset zony. Uzivateli se
 * prevadi az pri cteni, a to na spojeni, aby o tom nemusel vedet zadny grid - to resi
 * SessionTimeZoneMiddleware z adt/doctrine-components (a testuje se tam).
 */

require __DIR__ . '/bootstrap.php';


class TestLogRow
{
	use CreatedAtUtc;
	use UpdatedAtUtc;
}


test('razitko vznika v UTC bez ohledu na zonu aplikace', function () {
	$puvodni = date_default_timezone_get();
	date_default_timezone_set('Europe/Prague');

	try {
		$row = new TestLogRow();
		$row->stampCreatedAtUtc();

		Assert::same('UTC', $row->getCreatedAt()->getTimezone()->getName());
		// a je to opravdu tentyz okamzik, ne prestitkovany lokalni cas
		Assert::true(abs($row->getCreatedAt()->getTimestamp() - time()) < 5);
	} finally {
		date_default_timezone_set($puvodni);
	}
});


test('cas zapsany volajicim se neprepisuje', function () {
	// import starsich dat nebo prenos z jineho systemu - razitko uz hodnotu ma
	$row = new TestLogRow();
	$row->setCreatedAt(new DateTimeImmutable('2020-01-01 00:00:00', new DateTimeZone('UTC')));
	$row->stampCreatedAtUtc();

	Assert::same('2020-01-01', $row->getCreatedAt()->format('Y-m-d'));
});


test('updated_at se razitkuje take v UTC', function () {
	$puvodni = date_default_timezone_get();
	date_default_timezone_set('Europe/Prague');

	try {
		$row = new TestLogRow();
		$row->stampUpdatedAtUtc();

		Assert::same('UTC', $row->getUpdatedAt()->getTimezone()->getName());
	} finally {
		date_default_timezone_set($puvodni);
	}
});
