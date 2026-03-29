<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model;

class FileUploadRules
{
	public const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

	public const array ALLOWED_MIME_TYPES = [
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
	];
}
