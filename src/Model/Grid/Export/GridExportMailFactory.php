<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use ADT\Exporter\Model\Entities\Export;
use ADT\Exporter\Model\Service\ExportFileStorage;
use ADT\Exporter\Model\Service\ExportMailFactory;
use Nette\Localization\Translator;
use Nette\Mail\Message;

/**
 * E-mail s hotovým exportem: soubor v příloze a odkaz ke stažení po přihlášení. Exporter zprávu posílá přímo přes
 * Nette Mailer, odesílatele a vzhled proto doplní projekt v createMessage().
 */
class GridExportMailFactory implements ExportMailFactory
{
	public function __construct(
		private readonly ExportFileStorage $fileStorage,
		protected readonly Translator $translator,
	) {
	}

	public function create(Export $export, string $downloadLink): Message
	{
		$name = $export->getIdentifier();
		$count = 0;
		foreach ($export->getSections() as $section) {
			$count += count($section['ids'] ?? $section['rows'] ?? []);
		}

		$body = htmlspecialchars($this->translator->translate('fcadmin.datagrid.columnFilters.export.body', ['name' => $name, 'count' => $count]))
			. '<br><br>' . htmlspecialchars($this->translator->translate('fcadmin.datagrid.columnFilters.export.link', ['link' => $downloadLink]));

		$attachments = [];
		if ($path = $this->fileStorage->getLocalPath($export)) {
			$csv = str_ends_with((string) $export->getFileName(), '.' . GridExportCsvGenerator::FORMAT);
			$attachments[] = [
				'name' => (string) $export->getFileName(),
				'content' => (string) file_get_contents($path),
				'contentType' => $csv ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			];
		}

		return $this->createMessage((string) $export->getEmail(), $this->translator->translate('fcadmin.datagrid.columnFilters.export.subject', ['name' => $name]), $body, $attachments);
	}

	/** @param list<array{name: string, content: string, contentType: string}> $attachments */
	protected function createMessage(string $email, string $subject, string $htmlBody, array $attachments): Message
	{
		$message = (new Message())
			->addTo($email)
			->setSubject($subject)
			->setHtmlBody($htmlBody);
		foreach ($attachments as $attachment) {
			$message->addAttachment($attachment['name'], $attachment['content'], $attachment['contentType']);
		}

		return $message;
	}
}
