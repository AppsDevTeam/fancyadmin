<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\Entity\BiometricSignatureInterface;
use ADT\BiometricSignature\Exception\CodeNotSentException;
use ADT\BiometricSignature\SigningHandler;
use ADT\FancyAdmin\Model\Entities\SignedDocument;

/**
 * Co knihovně adt/biometric-signature dodává podpis na tabletu (TabletSigning): obsah důkazního
 * záznamu a podepsané PDF - obojí z podepsaného dokumentu, jeho žádosti a TabletSignHandler
 * projektu. Kód se neposílá, podpis na tabletu provozovatele se dokončuje rovnou.
 *
 * @internal vytváří TabletSigning
 */
final class TabletSigningHandler implements SigningHandler
{
	public function __construct(
		private readonly TabletSignHandler $handler,
	) {
	}

	public function sendCode(BiometricSignatureInterface $signature, string $code): void
	{
		throw new CodeNotSentException('Podpis na tabletu se kódem nepotvrzuje.');
	}

	public function getEvidenceSubject(BiometricSignatureInterface $signature): array
	{
		assert($signature instanceof SignedDocument);
		$request = $signature->getRequest();

		return [
			'document' => [
				'title' => $signature->getTitle(),
				'type' => $signature->getType(),
				'subjectId' => $signature->getSubjectId(),
				'data' => $signature->getData(),
			],
			'tablet' => $request === null ? null : [
				'name' => $request->getTabletName(),
				'requestedAt' => $request->getCreatedAt()->format(DATE_ATOM),
				'requesterId' => $request->getRequesterId(),
				'requesterName' => $request->getRequesterName(),
			],
			'subject' => $this->handler->getEvidenceSubject($signature),
		];
	}

	public function onVerified(BiometricSignatureInterface $signature): void
	{
		assert($signature instanceof SignedDocument);
		$pdf = $this->handler->createSignedPdf($signature);
		if ($pdf !== null) {
			$signature->setFile($pdf->file);
			$signature->recordPdf($pdf->content);
		}
	}
}
