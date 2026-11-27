<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\Clock\SystemClock;
use ADT\BiometricSignature\Evidence\Evidence;
use ADT\BiometricSignature\Exception\SignatureStampException;
use ADT\BiometricSignature\Pdf\SignatureStamp;
use ADT\BiometricSignature\Pdf\SignatureStamper;
use ADT\BiometricSignature\SignatureCapture;
use ADT\BiometricSignature\SignatureId;
use ADT\FancyAdmin\Model\Entities\DocumentSignature;
use ADT\FancyAdmin\Model\Entities\SignedDocument;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Psr\Clock\ClockInterface;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Podpis dokumentů na tabletu (docs/tablets.md, Podpis dokumentu). Žádost může nést víc
 * dokumentů, PDF si místa pro podpis označí samo (SignatureBox z adt/biometric-signature)
 * a podepisující se do každého místa podepíše zvlášť - nakreslený podpis smí potvrdit i do
 * dalších míst. Po odeslání uloží každý dokument (SignedDocument) a každý nakreslený podpis
 * (DocumentSignature) s důkazním záznamem, podpisy vloží do PDF na jejich místa, dokončí
 * podpis bez kódu (tablet patří provozovateli, totožnost ověřil personál) a žádost označí jako
 * podepsanou. Projekt přes TabletSignHandler dodá dokumenty a podepsaná PDF uloží.
 *
 * Bez handleru projektu, entit nebo balíčku adt/biometric-signature je podpis vypnutý
 * (isEnabled()) a tablety slouží jen k otevírání formulářů projektu. Třída proto na balíčku
 * při načtení nezávisí.
 */
class TabletSigning
{
	/**
	 * Jak dlouho se PDF žádosti drží v mezipaměti. Stránka podpisu se po nečinnosti vrací na
	 * čekací stránku (TabletDevicePresenterTrait::TABLET_SIGN_IDLE_RETURN), tohle je rezerva.
	 */
	private const string PDF_CACHE_EXPIRE = '2 hours';

	/** Klíč dokumentu v adrese náhledu a ve formuláři */
	private const string KEY_PATTERN = '~^[A-Za-z0-9_-]{1,64}$~';

	/** @var \WeakMap<TabletSignDocument, list<TabletSignPlace>> */
	private \WeakMap $places;

	/**
	 * @param class-string<SignedDocument>|null $signedDocumentClass entita podepsaného dokumentu projektu
	 * @param class-string<DocumentSignature>|null $documentSignatureClass entita podpisu projektu
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly TabletService $tabletService,
		private readonly ?TabletSignHandler $handler = null,
		private readonly ?string $signedDocumentClass = null,
		private readonly ?string $documentSignatureClass = null,
		private readonly ?ClockInterface $clock = null,
		/** Mezipaměť PDF žádosti - bez ní se PDF generuje při každém požadavku znovu */
		private readonly ?Storage $storage = null,
	) {
		$this->places = new \WeakMap();
	}

	public function isEnabled(): bool
	{
		return $this->handler !== null
			&& $this->signedDocumentClass !== null
			&& $this->documentSignatureClass !== null
			&& class_exists(SignatureCapture::class);
	}

	/**
	 * Dokumenty, které tablet k žádosti ukáže - klíč dokumentu => dokument (číselný klíč je
	 * v poli int, při použití ho přetypovat). null = žádost už nejde podepsat.
	 *
	 * @return array<int|string, TabletSignDocument>|null
	 */
	public function getDocuments(TabletSignRequest $request): ?array
	{
		$documents = $this->getHandler()->getDocuments($request);
		if (!$documents) {
			return null;
		}

		$keyed = [];
		foreach (array_values($documents) as $index => $document) {
			$key = $document->key ?? (string) ($index + 1);
			if (!preg_match(self::KEY_PATTERN, $key) || isset($keyed[$key])) {
				throw new \LogicException(sprintf('Klíč dokumentu k podpisu "%s" musí být v žádosti jedinečný a jen z písmen bez diakritiky, číslic, - a _ (nejvýš 64 znaků).', $key));
			}

			$keyed[$key] = $this->cachePdf($request, $key, $document);
		}

		return $keyed;
	}

	/**
	 * PDF dokumentu se vygeneruje jednou za žádost: stránka podpisu (místa pro podpis), náhled
	 * i podpis (otisk, vložení podpisů) pak pracují se stejnými bajty, jaké podepisující viděl -
	 * znovu vygenerované PDF by se lišilo aspoň časem vytvoření. A generování je pomalé.
	 */
	private function cachePdf(TabletSignRequest $request, string $key, TabletSignDocument $document): TabletSignDocument
	{
		$id = $request->getId();
		if ($this->storage === null || $id === null || !$document->hasPdf()) {
			return $document;
		}

		$cache = new Cache($this->storage, 'fancyadmin.tabletSign');

		return $document->withPdf(fn (): string => $cache->load(
			$this->getPdfCacheKey($request, $key),
			function (&$dependencies) use ($document): string {
				$dependencies[Cache::Expire] = self::PDF_CACHE_EXPIRE;

				return (string) $document->getPdf();
			},
		));
	}

	private function getPdfCacheKey(TabletSignRequest $request, string $key): string
	{
		return 'pdf-' . $request->getId() . '-' . $key;
	}

	/**
	 * Kam se do dokumentu podepisuje: místa pro podpis označená v PDF, která patří člověku
	 * u tabletu (TabletSignDocument::$boxNames), v pořadí stránek. Dokument bez nich (bez PDF,
	 * PDF bez míst, PDF, které nejde přečíst, nebo projekt bez setasign/fpdi) má jedno místo
	 * pod náhledem.
	 *
	 * @return list<TabletSignPlace>
	 */
	public function getPlaces(string $key, TabletSignDocument $document): array
	{
		return $this->places[$document] ??= $this->findPlaces($key, $document);
	}

	/**
	 * Podpis otevřené žádosti: uloží podepsané dokumenty a podpisy s důkazními záznamy, vloží
	 * podpisy do PDF (TabletSignHandler::createSignedPdf()) a žádost vyřídí. Vše se uloží
	 * najednou - když cokoli selže, nezůstane nic a podepsat jde znovu.
	 *
	 * @return list<SignedDocument>
	 * @throws TabletSignIncompleteException některé místo není podepsané nebo podpis místa chybí
	 */
	public function sign(TabletSignRequest $request, TabletSignSubmission $submission, ?string $ipAddress = null, ?string $userAgent = null): array
	{
		if (!$request->isOpen()) {
			throw new \LogicException('Podepsat jde jen žádost, kterou tablet právě zobrazuje.');
		}

		$handler = $this->getHandler();
		$documents = $this->getDocuments($request);
		if ($documents === null) {
			throw new \LogicException('Žádost už nejde podepsat - projekt k ní nevrátil žádný dokument.');
		}

		// každé místo každého dokumentu má podpis, který stránka poslala
		$places = [];
		foreach ($documents as $key => $document) {
			$key = (string) $key; // číselný klíč pole je int
			if ($document->error !== null) {
				throw new \LogicException(sprintf('Dokument "%s" nejde podepsat: %s', $document->title, $document->error));
			}

			foreach ($this->getPlaces($key, $document) as $place) {
				$signatureId = $submission->placements[$place->getId()] ?? null;
				if ($signatureId === null || !isset($submission->signatures[$signatureId])) {
					throw new TabletSignIncompleteException(sprintf('Místo %s dokumentu "%s" není podepsané.', $place->getId(), $document->title));
				}

				$places[] = [$place, $signatureId];
			}
		}

		if (count($submission->placements) !== count($places)) {
			throw new TabletSignIncompleteException('Stránka poslala podpis místa, které dokument nemá.');
		}

		$now = $this->getClock()->now();

		// Vše v jedné transakci - projekt v createSignedPdf() ukládá soubory a může sám flushovat;
		// když cokoli selže, neuloží se nic a podepsat jde znovu
		$connection = $this->em->getConnection();
		$connection->beginTransaction();
		try {
			[$signedDocuments, $signatures] = $this->store($request, $documents, $places, $submission, $now, $ipAddress, $userAgent);
			$connection->commit();
		} catch (\Throwable $e) {
			$connection->rollBack();
			throw $e;
		}

		if ($this->storage !== null) {
			$cache = new Cache($this->storage, 'fancyadmin.tabletSign');
			foreach (array_keys($documents) as $key) {
				$cache->remove($this->getPdfCacheKey($request, (string) $key));
			}
		}
		$handler->onSigned($request, array_values($signedDocuments), array_values($signatures));

		return array_values($signedDocuments);
	}

	/**
	 * Uloží podepsané dokumenty a podpisy, vloží podpisy do PDF (projekt je uloží) a žádost vyřídí.
	 *
	 * @param array<int|string, TabletSignDocument> $documents
	 * @param list<array{TabletSignPlace, string}> $places
	 * @return array{array<int|string, SignedDocument>, array<string, DocumentSignature>}
	 */
	private function store(TabletSignRequest $request, array $documents, array $places, TabletSignSubmission $submission, \DateTimeImmutable $now, ?string $ipAddress, ?string $userAgent): array
	{
		$handler = $this->getHandler();

		// ID obálky spojuje žádost, podepsané dokumenty a auditní stopu
		if ($request->getEnvelopeId() === null) {
			$request->setEnvelopeId(SignatureId::generate($request->getCreatedAt()));
		}

		// převzít jde jen vlastní podpis - podpis jednoho podepisujícího nesmí být v místě jiného
		$signers = [];
		foreach ($places as [$place, $signatureId]) {
			$signer = $documents[$place->document]->signerName;
			if (array_key_exists($signatureId, $signers) && $signers[$signatureId] !== $signer) {
				throw new TabletSignIncompleteException(sprintf('Podpis %s je v místech různých podepisujících.', $signatureId));
			}

			$signers[$signatureId] = $signer;
		}

		/** @var array<int|string, SignedDocument> $signedDocuments */
		$signedDocuments = [];
		foreach ($documents as $key => $document) {
			$key = (string) $key; // číselný klíč pole je int
			/** @var SignedDocument $signed */
			$signed = $this->signedDocumentClass::createForRequest($request, $key, $document, $now);
			$pdf = $document->getPdf();
			if ($pdf !== null) {
				$signed->recordOriginalPdf($pdf);
			}

			$this->em->persist($signed);
			$signedDocuments[$key] = $signed;
		}

		// podpisy v pořadí, v jakém je stránka vložila do míst; nepoužitý (přepsaný novým) se neukládá
		/** @var array<string, DocumentSignature> $signatures */
		$signatures = [];
		$placements = [];
		// každé podepsané místo má vlastní ID a čas - tisknou se pod podpis do PDF a stojí v auditní stopě
		/** @var array<string, SignatureStamp> $stamps ID místa => podpis s popiskem */
		$stamps = [];
		foreach ($places as [$place, $signatureId]) {
			$signedAt = $this->getPlacedAt($request, $submission, $place, $now);
			$placementId = SignatureId::generate($signedAt);
			$placements[$signatureId][] = [
				'document' => $place->document,
				'box' => $place->box,
				'id' => $placementId,
				'signedAt' => $signedAt->format(DATE_ATOM),
			];
			$stamps[$place->getId()] = new SignatureStamp($submission->signatures[$signatureId]->imageDataUri, $placementId, $signedAt);
			$signatures[$signatureId] ??= $this->documentSignatureClass::createForRequest($request, $submission->signatures[$signatureId], $now)
				->setRequestContext($ipAddress, $userAgent);
		}

		foreach ($signatures as $signatureId => $signature) {
			$signature->setPlacements($placements[$signatureId]);
			// na tabletu provozovatele bez kódu - důkazní záznam vzniká rovnou ("onSite": true)
			$signature->markVerified($now, Evidence::build($signature, $this->getEvidenceSubject($signature, $signedDocuments, $signers[$signatureId]), $now));
			$this->em->persist($signature);
		}

		// podpisy do PDF na jejich místa, uložení podepsaných PDF projektem
		foreach ($documents as $key => $document) {
			$key = (string) $key; // číselný klíč pole je int
			$images = [];
			$documentSignatures = [];
			foreach ($places as [$place, $signatureId]) {
				if ($place->document !== $key) {
					continue;
				}

				$documentSignatures[$signatureId] = $signatures[$signatureId];
				if ($place->box !== null) {
					$images[$place->box] = $stamps[$place->getId()];
				}
			}

			$pdf = $document->getPdf();
			$stamped = $pdf !== null && $images ? (new SignatureStamper())->stampBoxes($pdf, $images) : null;
			$signedPdf = $handler->createSignedPdf($signedDocuments[$key], $stamped, array_values($documentSignatures));
			if ($signedPdf !== null) {
				$signedDocuments[$key]->setFile($signedPdf->file);
				$signedDocuments[$key]->recordPdf($signedPdf->content);
			}
		}

		$pageCounts = [];
		foreach ($documents as $key => $document) {
			$pageCounts[(string) $key] = $this->countPages($document->getPdf());
		}

		$request->recordAuditLog(TabletSignAuditLog::build($request, $documents, $signedDocuments, $signatures, $signers, $submission->viewed, $pageCounts, $now)->toJson());

		$this->tabletService->finish($request, $now); // flush

		return [$signedDocuments, $signatures];
	}

	/**
	 * Důkazní záznam podpisu: kdo podepisoval (TabletSignDocument::$signerName), do kterých
	 * dokumentů a míst podpis vložil (s otiskem PDF, které viděl), komu a odkud to obsluha
	 * poslala, a co dodá projekt.
	 *
	 * @param array<int|string, SignedDocument> $documents
	 * @return array<mixed>
	 */
	private function getEvidenceSubject(DocumentSignature $signature, array $documents, ?string $signerName): array
	{
		$request = $signature->getRequest();
		$placed = [];
		foreach ($signature->getPlacements() as $placement) {
			$document = $documents[$placement['document']];
			$placed[$placement['document']] ??= [
				'key' => $document->getDocumentKey(),
				'title' => $document->getTitle(),
				'type' => $document->getType(),
				'subjectId' => $document->getSubjectId(),
				'data' => $document->getData(),
				'pdfSha256' => $document->getOriginalPdfHash(),
				'boxes' => [],
				'signatures' => [],
			];
			$placed[$placement['document']]['boxes'][] = $placement['box'];
			// ID a čas místa, jak stojí pod podpisem v PDF a v auditní stopě
			$placed[$placement['document']]['signatures'][] = [
				'id' => $placement['id'] ?? null,
				'box' => $placement['box'],
				'signedAt' => $placement['signedAt'] ?? null,
			];
		}

		return [
			'signerName' => $signerName,
			'envelopeId' => $request?->getEnvelopeId(),
			'documents' => array_values($placed),
			'tablet' => $request === null ? null : [
				'name' => $request->getTabletName(),
				'requestedAt' => $request->getCreatedAt()->format(DATE_ATOM),
				'requesterId' => $request->getRequesterId(),
				'requesterName' => $request->getRequesterName(),
			],
			'subject' => $this->getHandler()->getEvidenceSubject($signature),
		];
	}

	/**
	 * Kdy se podepisující do místa podepsal: stránka pošle, před kolika ms od odeslání to bylo
	 * (TabletSignSubmission::$placedAgo), a čas se dopočítá od času serveru. Nejdřív v okamžiku,
	 * kdy tablet žádost otevřel - údaj ze stránky se dá podvrhnout.
	 */
	private function getPlacedAt(TabletSignRequest $request, TabletSignSubmission $submission, TabletSignPlace $place, \DateTimeImmutable $now): \DateTimeImmutable
	{
		$opened = $request->getOpenedAt() ?? $request->getCreatedAt();
		$longest = max(0, ($now->getTimestamp() - $opened->getTimestamp()) * 1000);
		$ago = min($submission->placedAgo[$place->getId()] ?? 0, $longest);

		return $ago === 0 ? $now : $now->modify(sprintf('-%d milliseconds', $ago));
	}

	/** Počet stran PDF do auditní stopy, null = bez PDF, bez setasign/fpdi, nebo PDF nejde přečíst */
	private function countPages(?string $pdf): ?int
	{
		if ($pdf === null || !class_exists(\setasign\Fpdi\PdfParser\PdfParser::class)) {
			return null;
		}

		try {
			$reader = new \setasign\Fpdi\PdfReader\PdfReader(new \setasign\Fpdi\PdfParser\PdfParser(\setasign\Fpdi\PdfParser\StreamReader::createByString($pdf)));

			return $reader->getPageCount() ?: null;
		} catch (\Throwable) {
			return null;
		}
	}

	/** @return list<TabletSignPlace> */
	private function findPlaces(string $key, TabletSignDocument $document): array
	{
		$pdf = $document->hasPdf() && class_exists(\setasign\Fpdi\PdfParser\PdfParser::class) ? $document->getPdf() : null;
		$places = [];

		if ($pdf !== null) {
			try {
				foreach ((new SignatureStamper())->findBoxes($pdf) as $index => $box) {
					if ($document->boxNames === null || in_array($box->name, $document->boxNames, true)) {
						$places[] = new TabletSignPlace($key, $index, $box);
					}
				}
			} catch (SignatureStampException | \LogicException $e) {
				// náhled se v prohlížeči vykreslí i tak, podepíše se pod ním
				Debugger::log($e, ILogger::WARNING);
				$places = [];
			}
		}

		return $places ?: [new TabletSignPlace($key)];
	}

	private function getHandler(): TabletSignHandler
	{
		if (!$this->isEnabled()) {
			throw new \LogicException('Podpis na tabletu není zapnutý: chybí služba TabletSignHandler projektu, entity podepsaného dokumentu a podpisu (fancyadmin: tablet: signedDocumentEntity, documentSignatureEntity) nebo balíček adt/biometric-signature.');
		}

		return $this->handler;
	}

	private function getClock(): ClockInterface
	{
		return $this->clock ?? new SystemClock();
	}
}
