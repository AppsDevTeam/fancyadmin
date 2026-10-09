<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\FileUploadRules;
use Tester\Assert;

/**
 * Pravidla pro nahravani souboru.
 *
 * Hodnoty se propisuji do validaci formularu, takze jsou tu doslova - zmena limitu
 * nebo seznamu typu je vedome rozhodnuti, ne preklep.
 */

require __DIR__ . '/bootstrap.php';


test('limit velikosti je 10 MB', function () {
	Assert::same(10 * 1024 * 1024, FileUploadRules::MAX_FILE_SIZE);
	Assert::same(10485760, FileUploadRules::MAX_FILE_SIZE);
});


test('seznam povolenych MIME typu', function () {
	Assert::same([
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg+xml',
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'text/csv',
		'text/plain',
		'application/zip',
	], FileUploadRules::ALLOWED_MIME_TYPES);
});


test('spustitelne typy v seznamu nejsou', function () {
	foreach (['application/x-php', 'text/html', 'application/javascript', 'application/x-httpd-php', 'text/x-php'] as $type) {
		Assert::false(in_array($type, FileUploadRules::ALLOWED_MIME_TYPES, true), $type);
	}
});


test('seznam nema duplicity', function () {
	Assert::same(FileUploadRules::ALLOWED_MIME_TYPES, array_values(array_unique(FileUploadRules::ALLOWED_MIME_TYPES)));
});
