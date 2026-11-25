<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\UserAgentSummary;
use ADT\FancyAdmin\Model\Entities\DocumentSignature;
use ADT\FancyAdmin\Model\Entities\SignedDocument;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Auditní stopa podpisu na tabletu: co bylo v obálce (žádosti), kdo ji poslal a kam, kdo, kdy,
 * odkud a čím podepsal které místo kterého dokumentu a které dokumenty mu stránka zobrazila.
 *
 * Vzniká jednou při dokončení podpisu (TabletSigning::sign()) a ukládá se doslova k žádosti
 * (TabletSignRequest::recordAuditLog()) i s otiskem SHA-256. ID podpisů a časy v ní jsou stejné,
 * jaké stojí pod podpisy v podepsaných PDF, takže dokument jde se stopou spárovat.
 *
 * Stopu počítá a ukládá aplikace sama - nezávislé potvrzení času a neměnnosti dá až elektronická
 * pečeť podepsaných PDF (docs/tablets.md, Auditní stopa).
 */
final class TabletSignAuditLog
{
	public const string FORMAT = 'adt/fancyadmin-tablet-sign-audit';

	public const int VERSION = 1;

	/** Podpis nakreslený rukou na dotykovém zařízení, se záznamem průběhu psaní */
	public const string METHOD_HANDWRITTEN = 'handwritten';

	/** Totožnost ověřil personál na místě (tablet provozovatele, bez kódu z SMS) */
	public const string IDENTITY_ON_SITE = 'onSite';

	/** @param array<string, mixed> $data */
	private function __construct(
		private readonly array $data,
	) {
	}

	/**
	 * @param array<int|string, TabletSignDocument> $documents klíč dokumentu => dokument žádosti
	 * @param array<int|string, SignedDocument> $signedDocuments klíč dokumentu => podepsaný dokument
	 * @param array<string, DocumentSignature> $signatures podpisy s místy (getPlacements() s ID a časem)
	 * @param array<string, string|null> $signers podpis => jméno podepisujícího
	 * @param list<string> $viewed klíče dokumentů, které stránka zobrazila
	 * @param array<string, int|null> $pageCounts klíč dokumentu => počet stran
	 */
	public static function build(
		TabletSignRequest $request,
		array $documents,
		array $signedDocuments,
		array $signatures,
		array $signers,
		array $viewed,
		array $pageCounts,
		\DateTimeImmutable $now,
	): self {
		$numbers = [];
		$documentRows = [];
		foreach ($signedDocuments as $key => $signed) {
			$key = (string) $key;
			$numbers[$key] = count($numbers) + 1;
			$documentRows[] = [
				'number' => $numbers[$key],
				'key' => $key,
				'title' => $signed->getTitle(),
				'type' => $signed->getType(),
				'mimeType' => $signed->getOriginalPdfHash() === null ? null : 'application/pdf',
				'pages' => $pageCounts[$key] ?? null,
				'originalSha256' => $signed->getOriginalPdfHash(),
				'signedSha256' => $signed->getPdfHash(),
				'displayed' => in_array($key, $viewed, true),
			];
		}

		$signerRows = [];
		foreach ($signatures as $signatureId => $signature) {
			$name = $signers[$signatureId] ?? null;
			$signerKey = $name ?? '';
			$signerRows[$signerKey] ??= [
				'name' => $name,
				'identity' => [
					'method' => self::IDENTITY_ON_SITE,
					'verifiedBy' => ['id' => $request->getRequesterId(), 'name' => $request->getRequesterName()],
				],
				'signatures' => [],
			];

			foreach ($signature->getPlacements() as $placement) {
				$signerRows[$signerKey]['signatures'][] = [
					'id' => $placement['id'] ?? null,
					'document' => $numbers[$placement['document']] ?? null,
					'documentKey' => $placement['document'],
					'box' => $placement['box'],
					'method' => self::METHOD_HANDWRITTEN,
					'signedAt' => $placement['signedAt'] ?? $signature->getCreatedAt()->format(DATE_ATOM),
					'ipAddress' => $signature->getIpAddress(),
					'userAgent' => $signature->getUserAgent(),
					'browser' => UserAgentSummary::describe($signature->getUserAgent()),
					// důkazní záznam nakresleného podpisu (obrázek, průběh psaní) - DocumentSignature
					'evidenceSha256' => $signature->getDocumentHash(),
				];
			}
		}

		foreach ($signerRows as &$signer) {
			usort($signer['signatures'], fn (array $a, array $b): int => [$a['signedAt'], $a['id']] <=> [$b['signedAt'], $b['id']]);
		}
		unset($signer);

		return new self([
			'format' => self::FORMAT,
			'version' => self::VERSION,
			'envelope' => [
				'id' => $request->getEnvelopeId(),
				'title' => $request->getTitle(),
				'sender' => ['id' => $request->getRequesterId(), 'name' => $request->getRequesterName()],
				'tablet' => $request->getTabletName(),
				'requestedAt' => $request->getCreatedAt()->format(DATE_ATOM),
				'openedAt' => $request->getOpenedAt()?->format(DATE_ATOM),
				'completedAt' => $now->format(DATE_ATOM),
				'timezone' => $now->getTimezone()->getName(),
			],
			'documents' => $documentRows,
			'signers' => array_values($signerRows),
		]);
	}

	/** @throws \InvalidArgumentException text není auditní stopa tohoto formátu */
	public static function fromJson(string $json): self
	{
		try {
			$data = Json::decode($json, forceArrays: true);
		} catch (JsonException $e) {
			throw new \InvalidArgumentException('Auditní stopu nejde přečíst.', previous: $e);
		}

		if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
			throw new \InvalidArgumentException('Text není auditní stopa podpisu na tabletu.');
		}

		return new self($data);
	}

	/** Auditní stopa žádosti, null = žádost není podepsaná (nebo byla podepsaná před zavedením stopy) */
	public static function ofRequest(TabletSignRequest $request): ?self
	{
		$json = $request->getAuditLogJson();

		return $json === null ? null : self::fromJson($json);
	}

	/** Text, který se ukládá k žádosti a ze kterého se počítá otisk */
	public function toJson(): string
	{
		return Json::encode($this->data, pretty: true);
	}

	/** @return array<string, mixed> */
	public function toArray(): array
	{
		return $this->data;
	}
}
