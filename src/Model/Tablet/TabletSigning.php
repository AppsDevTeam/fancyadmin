<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\BiometricSigning;
use ADT\BiometricSignature\Clock\SystemClock;
use ADT\BiometricSignature\SignatureCapture;
use ADT\BiometricSignature\SigningPolicy;
use ADT\FancyAdmin\Model\Entities\SignedDocument;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Podpis dokumentu na tabletu (docs/tablets.md, Podpis dokumentu): k otevřené žádosti o podpis
 * uloží biometrický podpis jako podepsaný dokument (SignedDocument), dokončí ho bez kódu -
 * tablet patří provozovateli, totožnost ověřil personál (adt/biometric-signature,
 * completeOnSite) - a žádost označí jako podepsanou. Projekt přes TabletSignHandler dodá, co
 * se podepisuje, a podepsané PDF.
 *
 * Bez handleru projektu nebo bez balíčku adt/biometric-signature je podpis vypnutý
 * (isEnabled()) a tablety slouží jen k otevírání formulářů projektu. Třída proto na balíčku
 * při načtení nezávisí - co mu dodává tablet, je v TabletSigningHandler.
 */
class TabletSigning
{
	private ?BiometricSigning $signing = null;

	/** @param class-string<SignedDocument>|null $signedDocumentClass entita podepsaného dokumentu projektu */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly TabletService $tabletService,
		private readonly ?TabletSignHandler $handler = null,
		private readonly ?string $signedDocumentClass = null,
		private readonly ?ClockInterface $clock = null,
	) {
	}

	public function isEnabled(): bool
	{
		return $this->handler !== null && $this->signedDocumentClass !== null && class_exists(BiometricSigning::class);
	}

	/** Co tablet k žádosti ukáže, null = žádost už nejde podepsat */
	public function getDocument(TabletSignRequest $request): ?TabletSignDocument
	{
		return $this->getHandler()->getDocument($request);
	}

	/**
	 * Podpis otevřené žádosti: uloží podpis, vytvoří důkazní záznam a podepsané PDF
	 * (TabletSignHandler::createSignedPdf()) a žádost vyřídí.
	 */
	public function sign(TabletSignRequest $request, SignatureCapture $capture, ?string $ipAddress = null, ?string $userAgent = null): SignedDocument
	{
		if (!$request->isOpen()) {
			throw new \LogicException('Podepsat jde jen žádost, kterou tablet právě zobrazuje.');
		}

		$handler = $this->getHandler();
		$now = $this->getClock()->now();

		/** @var SignedDocument $document */
		$document = $this->signedDocumentClass::createForRequest($request, $capture, $now);
		$document->setRequestContext($ipAddress, $userAgent);
		$this->em->persist($document);
		$this->em->flush();

		// důkazní záznam, podepsané PDF (TabletSigningHandler::onVerified) a flush
		$this->getSigning()->completeOnSite($document);

		$this->tabletService->finish($request, $now);
		$handler->onSigned($document);

		return $document;
	}

	private function getHandler(): TabletSignHandler
	{
		if (!$this->isEnabled()) {
			throw new \LogicException('Podpis na tabletu není zapnutý: chybí služba TabletSignHandler projektu, entita podepsaného dokumentu (fancyadmin: tablet: signedDocumentEntity) nebo balíček adt/biometric-signature.');
		}

		return $this->handler;
	}

	private function getClock(): ClockInterface
	{
		return $this->clock ?? new SystemClock();
	}

	private function getSigning(): BiometricSigning
	{
		// Vlastní instance s handlerem tabletu - nezávislá na BiometricSigning projektu (podpisy
		// potvrzované kódem mají svůj SigningHandler)
		return $this->signing ??= new BiometricSigning($this->em, new TabletSigningHandler($this->getHandler()), new SigningPolicy(), $this->getClock());
	}
}
