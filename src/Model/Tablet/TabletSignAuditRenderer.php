<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use Nette\Localization\Translator;

/**
 * Auditní stopa podpisu na tabletu (TabletSignAuditLog) jako samostatná stránka HTML: obálka,
 * dokumenty s otisky, podepisující a jejich podpisy. Projekt ji ukáže v administraci nebo z ní
 * svým nástrojem (mPDF, Chrome…) udělá PDF k podepsaným dokumentům.
 *
 * Texty jsou v překladech fcadmin.tablets.audit.*; časy se vypisují v časovém pásmu obálky.
 */
final class TabletSignAuditRenderer
{
	private const string DATE_FORMAT = 'j. n. Y H:i:s';

	public function __construct(
		private readonly Translator $translator,
	) {
	}

	/** null = žádost auditní stopu nemá (není podepsaná, nebo je z doby před jejím zavedením) */
	public function renderRequest(TabletSignRequest $request): ?string
	{
		$log = TabletSignAuditLog::ofRequest($request);

		return $log === null ? null : $this->render($log, $request->getAuditLogHash());
	}

	/** @param string|null $hash otisk uložené stopy (TabletSignRequest::getAuditLogHash()), vypíše se pod ni */
	public function render(TabletSignAuditLog $log, ?string $hash = null): string
	{
		$data = $log->toArray();
		$envelope = $data['envelope'];
		$timezone = new \DateTimeZone($envelope['timezone'] ?? date_default_timezone_get());
		$date = fn (?string $value): string => $value === null
			? $this->text('unknown')
			: (new \DateTimeImmutable($value))->setTimezone($timezone)->format(self::DATE_FORMAT);
		$or = fn (mixed $value): string => $value === null || $value === '' ? $this->text('unknown') : (string) $value;

		$documents = '';
		$pages = 0;
		foreach ($data['documents'] as $document) {
			$pages += (int) ($document['pages'] ?? 0);
			$lines = [];
			if ($document['originalSha256'] !== null) {
				$lines[] = $this->html('originalHash', ['hash' => $document['originalSha256']]);
			}
			if ($document['signedSha256'] !== null) {
				$lines[] = $this->html('finalHash', ['hash' => $document['signedSha256']]);
			}
			if ($document['mimeType'] !== null) {
				$lines[] = $this->html('fileType', ['type' => $document['mimeType']]);
			}
			if ($document['pages'] !== null) {
				$lines[] = $this->html('pages', ['pages' => $document['pages']]);
			}

			$documents .= sprintf(
				'<p class="doc"><strong>#%d - %s</strong>%s</p>',
				$document['number'],
				self::escape($document['title']),
				implode('', array_map(fn (string $line): string => '<br><span class="small">' . $line . '</span>', $lines)),
			);
		}

		$displayed = array_map(
			fn (array $document): string => '#' . $document['number'],
			array_filter($data['documents'], fn (array $document): bool => (bool) $document['displayed']),
		);

		$signers = '';
		foreach ($data['signers'] as $signer) {
			$items = [
				$this->html('sent', [
					'sender' => $or($envelope['sender']['name'] ?? null),
					'tablet' => $or($envelope['tablet'] ?? null),
					'date' => $date($envelope['requestedAt']),
				]),
			];
			if ($envelope['openedAt'] !== null) {
				$items[] = $this->html('opened', ['date' => $date($envelope['openedAt'])]);
			}
			if (($signer['identity']['method'] ?? null) === TabletSignAuditLog::IDENTITY_ON_SITE) {
				$items[] = $this->html('identityOnSite', ['name' => $or($signer['identity']['verifiedBy']['name'] ?? null)]);
			}

			$signatures = '';
			foreach ($signer['signatures'] as $signature) {
				$signatures .= '<li>' . $this->html('signature', [
					'id' => $or($signature['id']),
					'document' => $or($signature['document']),
					'ip' => $or($signature['ipAddress']),
					'browser' => $or($signature['browser'] ?? $signature['userAgent']),
					'date' => $date($signature['signedAt']),
				]) . '</li>';
			}

			$items[] = '<ul>' . $signatures . '</ul>';
			$items[] = $displayed
				? $this->html('displayed', ['documents' => implode(', ', $displayed)])
				: $this->html('displayedNone');

			$signers .= sprintf(
				'<div class="signer"><p><strong>%s</strong> <span class="state">%s</span></p><ul>%s</ul></div>',
				self::escape($signer['name'] ?? $this->text('unnamedSigner')),
				$this->html('signed'),
				implode('', array_map(fn (string $item): string => str_starts_with($item, '<ul>') ? $item : '<li>' . $item . '</li>', $items)),
			);
		}

		$rows = [
			[$this->html('envelopeId'), self::escape($or($envelope['id']))],
			[$this->html('envelopeTitle'), self::escape($or($envelope['title']))],
			[$this->html('sender'), self::escape($or($envelope['sender']['name'] ?? null))],
			[$this->html('tablet'), self::escape($or($envelope['tablet'] ?? null))],
			[$this->html('content'), $this->html('contentSummary', ['documents' => count($data['documents']), 'pages' => $pages]) . $documents],
			[$this->html('signersCount'), (string) count($data['signers'])],
			[$this->html('timezone'), self::escape(sprintf('%s (%s)', $timezone->getName(), (new \DateTimeImmutable($envelope['completedAt']))->setTimezone($timezone)->format('P')))],
			[$this->html('state'), '<span class="state">' . $this->html('completed', ['date' => $date($envelope['completedAt'])]) . '</span>'],
		];

		$table = implode('', array_map(fn (array $row): string => sprintf('<tr><th>%s:</th><td>%s</td></tr>', $row[0], $row[1]), $rows));

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $this->html('title') . '</title><style>'
			. 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#111;margin:24px}'
			. 'h1{font-size:15pt;margin:0 0 8px}h2{font-size:11pt;margin:18px 0 6px}'
			. 'table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:6px 8px;border-bottom:1px solid #ccc}'
			. 'th{width:28%;font-weight:normal}.small{font-size:8pt}.doc{margin:6px 0 0}.state{color:#1a7f37;font-weight:bold}'
			. '.signer{border-bottom:1px solid #ccc;padding-bottom:6px;margin-bottom:10px}ul{margin:4px 0 6px}li{margin:3px 0}.hash{font-size:8pt;color:#555;margin-top:16px}'
			. '</style></head><body>'
			. '<h1>' . $this->html('title') . '</h1><p>' . $this->html('intro') . '</p>'
			. '<h2>' . $this->html('envelope') . '</h2><table>' . $table . '</table>'
			. '<h2>' . $this->html('signers') . '</h2>' . $signers
			. ($hash === null ? '' : '<p class="hash">' . $this->html('hash', ['hash' => $hash]) . '</p>')
			. '</body></html>';
	}

	/** @param array<string, scalar> $parameters */
	private function text(string $key, array $parameters = []): string
	{
		$text = (string) $this->translator->translate('fcadmin.tablets.audit.' . $key, $parameters);

		// překladač, který parametry nedosazuje (nebo je čeká jinak), nesmí nechat %name% ve výpisu
		foreach ($parameters as $name => $value) {
			$text = str_replace('%' . $name . '%', (string) $value, $text);
		}

		return $text;
	}

	/** @param array<string, scalar> $parameters */
	private function html(string $key, array $parameters = []): string
	{
		return self::escape($this->text($key, $parameters));
	}

	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
