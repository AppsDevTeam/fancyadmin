<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat;

use ADT\AiChat\Entity\ConversationInterface;
use ADT\AiChat\Pseudonymization\PiiCheckListener;
use ADT\AiChat\Pseudonymization\PiiCheckUnavailableException;
use ADT\AiChat\Pseudonymization\PseudonymizedText;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Kontrola osobních údajů ve zprávě před odesláním do AI → auditní stopa. Do payloadu jdou
 * jen počty nahrazených údajů podle druhu kotvy, nikdy hodnoty. Klíč `qwen` (text kontroloval
 * LLM detektor, ne jen regulární výrazy) zůstává kvůli návaznosti na starší záznamy.
 */
final class AuditPiiCheckListener implements PiiCheckListener
{
	public const string ACTION = 'ai_pii_check';

	public function __construct(
		private readonly AuditLogger $auditLogger,
		private readonly AuditActor $auditActor,
	) {
	}

	public function checked(ConversationInterface $conversation, PseudonymizedText $result): void
	{
		$this->log(AuditLogger::OUTCOME_SUCCESS, [
			'conversation_id' => $conversation->getId(),
			'qwen' => $result->detectorChecked,
			'findings' => $result->findings,
		]);
	}

	public function unavailable(ConversationInterface $conversation, PiiCheckUnavailableException $exception): void
	{
		$this->log(AuditLogger::OUTCOME_FAILURE, [
			'conversation_id' => $conversation->getId(),
			'reason' => 'pii_check_unavailable',
		]);
	}

	/** @param array<string, mixed> $payload */
	private function log(string $outcome, array $payload): void
	{
		// audit_log je vždy v UTC; tah běží ve frontě, aktér má IP a prohlížeč null
		$this->auditLogger->log(self::ACTION, $outcome, new DateTimeImmutable('now', new DateTimeZone('UTC')), null, $this->auditActor->create(), $payload);
	}
}
