<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\TabletSigningEntities;

use ADT\BiometricSignature\Biometrics\BiometricsNormalizer;
use ADT\BiometricSignature\BiometricSigning;
use ADT\BiometricSignature\Evidence\EvidenceVerifier;
use ADT\BiometricSignature\SignatureCapture;
use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
use ADT\BiometricSignature\Exception\InvalidSignatureException;
use ADT\BiometricSignature\Pdf\SignatureBox;
use ADT\BiometricSignature\Pdf\SignatureStamper;
use ADT\FancyAdmin\Model\Entities\DocumentSignature as DocumentSignatureInterface;
use ADT\FancyAdmin\Model\Entities\DocumentSignatureTrait;
use ADT\FancyAdmin\Model\Entities\SignedDocument as SignedDocumentInterface;
use ADT\FancyAdmin\Model\Entities\SignedDocumentTrait;
use ADT\FancyAdmin\Model\Entities\Tablet as TabletInterface;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest as TabletSignRequestInterface;
use ADT\FancyAdmin\Model\Entities\TabletSignRequestTrait;
use ADT\FancyAdmin\Model\Entities\TabletTrait;
use ADT\FancyAdmin\Model\Tablet\SignedPdf;
use ADT\FancyAdmin\Model\Tablet\TabletService;
use ADT\FancyAdmin\Model\Tablet\TabletSignDocument;
use ADT\FancyAdmin\Model\Tablet\TabletSignHandler;
use ADT\FancyAdmin\Model\Tablet\TabletSignIncompleteException;
use ADT\FancyAdmin\Model\Tablet\TabletSignPlace;
use ADT\FancyAdmin\Model\Tablet\TabletSignSubmission;
use ADT\FancyAdmin\Model\Tablet\TabletSigning;
use ADT\FancyAdmin\Model\Tablet\TabletSignRequests;
use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestHttpResponse;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Psr\Clock\ClockInterface;
use Tester\Assert;
use Tester\Environment;

/**
 * Podpis dokumentů na tabletu (TabletSigning, docs/tablets.md): žádost nese víc dokumentů, PDF
 * si označí místa pro podpis a podepisující se do každého podepíše zvlášť (nakreslený podpis
 * smí potvrdit i do dalších míst). Po odeslání se uloží podepsané dokumenty a podpisy
 * s důkazními záznamy, podpisy se vloží do PDF na svá místa a žádost se vyřídí. Projekt
 * (TabletSignHandler) dodává jen dokumenty a ukládá podepsaná PDF.
 */

require __DIR__ . '/bootstrap.php';

if (!class_exists(BiometricSigning::class)) {
	Environment::skip('Podpis na tabletu potřebuje volitelný balíček adt/biometric-signature.');
}

if (!class_exists(\setasign\Fpdi\Fpdi::class) || !class_exists(\FPDF::class)) {
	Environment::skip('Místa pro podpis v PDF potřebují setasign/fpdi a setasign/fpdf.');
}


#[ORM\Entity]
class Tablet implements TabletInterface
{
	use Identifier;
	use TabletTrait;
}


#[ORM\Entity]
class TabletSignRequest implements TabletSignRequestInterface
{
	use Identifier;
	use TabletSignRequestTrait;
}


#[ORM\Entity]
class File implements \ADT\Files\Entities\File
{
	use Identifier;
	use \ADT\Files\Entities\FileTrait;
}


#[ORM\Entity]
#[ORM\Table(name: 'signed_document')]
class SignedDocument implements SignedDocumentInterface
{
	use Identifier;
	use SignedDocumentTrait;
}


#[ORM\Entity]
#[ORM\Table(name: 'document_signature')]
class DocumentSignature implements DocumentSignatureInterface
{
	use Identifier;
	use DocumentSignatureTrait;
}


/** Jedna otevřená žádost tabletu - TabletService se v testu jinam neptá */
final class OneRequest implements TabletSignRequests
{
	public function __construct(public ?TabletSignRequest $request = null)
	{
	}

	public function create(TabletInterface $tablet, string $url, string $title, DateTimeImmutable $now, DateTimeImmutable $expiresAt): TabletSignRequestInterface
	{
		return TabletSignRequest::create($tablet, $url, $title, $now, $expiresAt);
	}

	public function add(TabletSignRequestInterface $request): void
	{
		$this->request = $request;
	}

	public function findPending(TabletInterface $tablet, DateTimeImmutable $now): ?TabletSignRequestInterface
	{
		return $this->request?->isPending($now) ? $this->request : null;
	}

	public function findOpen(TabletInterface $tablet): ?TabletSignRequestInterface
	{
		return $this->request?->isOpen() ? $this->request : null;
	}

	public function findUnfinished(TabletInterface $tablet, DateTimeImmutable $now): array
	{
		return $this->request !== null && ($this->request->isOpen() || $this->request->isPending($now)) ? [$this->request] : [];
	}
}


/** Projekt: co se podepisuje a podepsané PDF - zaznamená, co po něm fancyadmin chtěl */
final class RecordingSignHandler implements TabletSignHandler
{
	/** @var list<string> */
	public array $calls = [];

	/** @var array<string, string|null> klíč dokumentu => PDF s vloženými podpisy */
	public array $stamped = [];

	/** @var array<string, list<DocumentSignatureInterface>> */
	public array $signatures = [];

	public ?\Throwable $pdfFailure = null;

	/** Kolikrát projekt generoval PDF smlouvy */
	public int $generated = 0;

	/** @var list<TabletSignDocument>|null */
	public ?array $documents;

	public function __construct()
	{
		$this->documents = $this->freshDocuments();
	}

	/**
	 * Dokumenty jako je projekt vytváří při každém požadavku znovu
	 *
	 * @return list<TabletSignDocument>
	 */
	public function freshDocuments(): array
	{
		return [
			// smlouva: místa klienta na 1. a 2. stránce, místo poradce podepíše později jinde
			new TabletSignDocument('Smlouva o poskytování služeb', fn (): string => contractPdf(++$this->generated), key: 'contract', signerName: 'Jan Novák', boxNames: ['client']),
			// souhlas bez PDF: jedno místo pod textem, vlastní záznam projektu
			new TabletSignDocument('Souhlas se zpracováním údajů', text: 'Souhlasím …', key: 'consent', type: 'consent', subjectId: '17'),
		];
	}

	public function getDocuments(TabletSignRequestInterface $request): ?array
	{
		return $this->documents;
	}

	public function getEvidenceSubject(DocumentSignatureInterface $signature): array
	{
		$this->calls[] = 'evidence';

		return ['client' => ['id' => 17, 'name' => 'Jan Novák']];
	}

	public function createSignedPdf(SignedDocumentInterface $document, ?string $stampedPdf, array $signatures): ?SignedPdf
	{
		$this->calls[] = 'pdf ' . $document->getDocumentKey();
		if ($this->pdfFailure !== null) {
			throw $this->pdfFailure;
		}

		$this->stamped[$document->getDocumentKey()] = $stampedPdf;
		$this->signatures[$document->getDocumentKey()] = $signatures;

		// otisky důkazních záznamů už jsou známé - projekt je může do PDF doplnit
		$content = $stampedPdf ?? '%PDF-generated ' . implode(',', array_map(fn ($signature) => $signature->getDocumentHash(), $signatures));

		return new SignedPdf((new File())->setTemporaryContent($content, $document->getDocumentKey() . '.pdf'), $content);
	}

	public function onSigned(TabletSignRequestInterface $request, array $documents, array $signatures): void
	{
		$this->calls[] = 'signed ' . count($documents) . '/' . count($signatures);
	}
}


final class FixedClock implements ClockInterface
{
	public function __construct(private readonly DateTimeImmutable $now)
	{
	}

	public function now(): DateTimeImmutable
	{
		return $this->now;
	}
}


/** Smlouva o dvou stránkách: místo klienta na 1. stránce, poradce na 1. a klienta na 2. stránce */
function contractPdf(int $version = 1): string
{
	$pdf = new \FPDF('P', 'pt', 'A4');
	$pdf->SetFont('Helvetica', '', 12);
	$pdf->AddPage();
	// každé vygenerování je jiné (jako čas vytvoření v PDF z mPDF)
	$pdf->Text(50, 60, 'Smlouva, vygenerovano ' . $version . '.');
	$pdf->Link(50, 600, 170, 57, SignatureBox::url('client'));
	$pdf->Link(320, 600, 170, 57, SignatureBox::url('advisor'));
	$pdf->AddPage();
	$pdf->Link(320, 700, 170, 57, SignatureBox::url('client'));

	return $pdf->Output('S');
}


/** @return array{image: string, biometrics: string} */
function signatureInput(float $shift = 0): array
{
	return [
		'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
		'biometrics' => json_encode([
			'version' => 1,
			'startedAt' => '2026-01-01T11:59:00.000Z',
			'canvas' => ['width' => 600, 'height' => 200, 'devicePixelRatio' => 2],
			'device' => ['userAgent' => 'Mozilla/5.0 (iPad)', 'maxTouchPoints' => 5, 'screen' => ['width' => 1024, 'height' => 1366]],
			'fields' => BiometricsNormalizer::FIELDS,
			'strokes' => [['type' => 'pen', 'points' => [
				[10.5 + $shift, 20.25, 0, 0.31, 12, -4, 1.1, 0.5, 0, 1, 1],
				[11.75 + $shift, 21, 4.17, 0.42, 13, -3, 1.08, 0.52, 0, 1, 1],
				[13 + $shift, 23.5, 8.33, 0.45, 13, -3, 1.07, 0.53, 0, 1, 1],
			]]],
		], JSON_THROW_ON_ERROR),
	];
}

/**
 * Co pošle stránka: podpis s1 do obou míst smlouvy (druhé místo ho převzalo), s2 pod souhlas;
 * s3 podepisující nakreslil, ale pak ho přepsal - v žádném místě nezůstal.
 *
 * @param array<string, string> $placements
 */
function submission(?array $placements = null): TabletSignSubmission
{
	return TabletSignSubmission::fromJson(json_encode([
		'signatures' => ['s1' => signatureInput(), 's2' => signatureInput(1), 's3' => signatureInput(2)],
		'placements' => $placements ?? ['contract:0' => 's1', 'contract:2' => 's1', 'consent:-' => 's2'],
	], JSON_THROW_ON_ERROR));
}

/** @return array{TabletSigning, TabletService, TabletSignRequest, RecordingSignHandler, TestEntityManager} */
function signing(DateTimeImmutable $now, ?string $documentClass = SignedDocument::class, bool $withHandler = true, ?string $signatureClass = DocumentSignature::class, ?\Nette\Caching\Storage $storage = null): array
{
	$em = new TestEntityManager();
	$requests = new OneRequest();
	$service = new TabletService(
		$em,
		new class implements \ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory {
			public function create(): \ADT\FancyAdmin\Model\Queries\TabletQuery
			{
				throw new \LogicException('Dotaz na tablety se v testu nečeká.');
			}
		},
		new class extends WebPushSender {
			public function __construct()
			{
			}

			public function isConfigured(): bool
			{
				return false;
			}
		},
		new TestTranslator(),
		new Request(new UrlScript('https://localhost/tablet/sign')),
		new TestHttpResponse(),
		$requests,
	);

	$tablet = (new Tablet())->setName('Recepce');
	$tablet->pair(str_repeat('a', 64), $now);
	$request = $service->send(
		$tablet,
		'/tablet/sign',
		'Smlouva a souhlas',
		fn (TabletSignRequestInterface $request) => $request->setSubject('order', '4321', ['client' => 17])->setRequester('5', 'Jana Nováková'),
		$now->modify('-2 minutes'),
	)->request;
	$service->takePendingForm($tablet, $now->modify('-1 minute'));

	$handler = new RecordingSignHandler();

	return [new TabletSigning($em, $service, $withHandler ? $handler : null, $documentClass, $signatureClass, new FixedClock($now), $storage), $service, $request, $handler, $em];
}

/** @param list<object> $entities @return list<object> */
function only(array $entities, string $class): array
{
	return array_values(array_filter($entities, fn (object $entity): bool => $entity instanceof $class));
}

$now = new DateTimeImmutable('2026-01-01 12:00:00');


test('místa pro podpis: označená v PDF pro člověka u tabletu, dokument bez nich má jedno místo pod náhledem', function () use ($now) {
	[$signing, , $request] = signing($now);
	$documents = $signing->getDocuments($request);
	Assert::same(['contract', 'consent'], array_keys($documents));

	// místo poradce (pořadí 1) patří někomu jinému - zůstane v PDF k pozdějšímu podpisu
	$contract = $signing->getPlaces('contract', $documents['contract']);
	Assert::same(['contract:0', 'contract:2'], array_map(fn (TabletSignPlace $place) => $place->getId(), $contract));
	Assert::same(['id' => 'contract:2', 'document' => 'contract', 'box' => 2, 'name' => 'client', 'page' => 2], array_slice($contract[1]->toArray(), 0, 5));
	Assert::true(abs(700.0 - $contract[1]->toArray()['y']) < 0.1);

	$consent = $signing->getPlaces('consent', $documents['consent']);
	Assert::same([['id' => 'consent:-', 'document' => 'consent', 'box' => null, 'name' => null, 'page' => null, 'x' => null, 'y' => null, 'width' => null, 'height' => null]], array_map(fn ($place) => $place->toArray(), $consent));
});


test('dokumenty bez klíče se číslují, klíč musí být v žádosti jedinečný', function () use ($now) {
	[$signing, , $request, $handler] = signing($now);
	$handler->documents = [new TabletSignDocument('A'), new TabletSignDocument('B')];
	Assert::same([1, 2], array_keys($signing->getDocuments($request)));

	$handler->documents = [new TabletSignDocument('A', key: 'x'), new TabletSignDocument('B', key: 'x')];
	Assert::exception(fn () => $signing->getDocuments($request), \LogicException::class, '~jedinečný~');

	// číselný klíč (např. ID smlouvy) projde celým podpisem
	$handler->documents = [new TabletSignDocument('Smlouva 42', key: '42')];
	$documents = $signing->sign($request, TabletSignSubmission::fromJson(json_encode(['signatures' => ['s1' => signatureInput()], 'placements' => ['42:-' => 's1']])));
	Assert::same('42', $documents[0]->getDocumentKey());

	$handler->documents = [];
	Assert::null($signing->getDocuments($request));
});


test('podpis uloží dokumenty a podpisy s důkazními záznamy, vloží podpisy do PDF na jejich místa a žádost vyřídí', function () use ($now) {
	[$signing, $service, $request, $handler, $em] = signing($now);

	$documents = $signing->sign($request, submission(), '192.0.2.10', 'Mozilla/5.0 (iPad)');

	// podepsané dokumenty: čeho se týkají (z dokumentu, jinak ze žádosti) a otisk originálu
	Assert::count(2, $documents);
	[$contract, $consent] = $documents;
	Assert::same($request, $contract->getRequest());
	Assert::same('contract', $contract->getDocumentKey());
	Assert::same('Smlouva o poskytování služeb', $contract->getTitle());
	Assert::same(['order', '4321', ['client' => 17]], [$contract->getType(), $contract->getSubjectId(), $contract->getData()]);
	Assert::same(['consent', '17'], [$consent->getType(), $consent->getSubjectId()]);
	Assert::same(hash('sha256', contractPdf(1)), $contract->getOriginalPdfHash());
	Assert::null($consent->getOriginalPdfHash());
	Assert::equal($now, $contract->getCreatedAt());

	// podpisy: s1 ve dvou místech smlouvy, s2 pod souhlasem; přepsaný s3 se neukládá
	$signatures = only($em->persisted, DocumentSignature::class);
	Assert::count(2, $signatures);
	[$first, $second] = $signatures;
	Assert::same([['document' => 'contract', 'box' => 0], ['document' => 'contract', 'box' => 2]], $first->getPlacements());
	Assert::same([['document' => 'consent', 'box' => null]], $second->getPlacements());

	// na tabletu provozovatele: bez kódu, ověřený rovnou
	Assert::true($first->isOnSite());
	Assert::true($first->isVerified());
	Assert::equal($now, $first->getVerifiedAt());
	Assert::same('', $first->getPhone());
	Assert::same('192.0.2.10', $first->getIpAddress());
	Assert::same($request, $first->getRequest());

	// důkazní záznam: kam se podpis vložil (s otiskem PDF, které podepisující viděl), komu to
	// obsluha poslala a co dodal projekt
	$evidence = json_decode($first->getEvidenceJson(), true, flags: JSON_THROW_ON_ERROR);
	Assert::true($evidence['onSite']);
	Assert::null($evidence['codeVerification']);
	Assert::same([[
		'key' => 'contract',
		'title' => 'Smlouva o poskytování služeb',
		'type' => 'order',
		'subjectId' => '4321',
		'data' => ['client' => 17],
		'pdfSha256' => hash('sha256', contractPdf(1)),
		'boxes' => [0, 2],
	]], $evidence['subject']['documents']);
	Assert::same('Jan Novák', $evidence['subject']['signerName']);
	Assert::same('Recepce', $evidence['subject']['tablet']['name']);
	Assert::same('Jana Nováková', $evidence['subject']['tablet']['requesterName']);
	Assert::same(['client' => ['id' => 17, 'name' => 'Jan Novák']], $evidence['subject']['subject']);
	Assert::true((new EvidenceVerifier())->verify($first)->isValid());
	Assert::true((new EvidenceVerifier())->verify($second)->isValid());

	// do smlouvy se podpis vložil do obou míst klienta, místo poradce zůstalo označené
	$left = (new SignatureStamper())->findBoxes($handler->stamped['contract']);
	Assert::same(['advisor'], array_map(fn (SignatureBox $box) => $box->name, $left));
	Assert::same([$first], $handler->signatures['contract']);
	// souhlas bez míst v PDF: PDF si vygeneroval projekt
	Assert::null($handler->stamped['consent']);
	Assert::same([$second], $handler->signatures['consent']);

	// podepsaná PDF od projektu: soubor a otisk obsahu
	Assert::type(File::class, $contract->getFile());
	Assert::same(hash('sha256', $handler->stamped['contract']), $contract->getPdfHash());
	Assert::same(hash('sha256', '%PDF-generated ' . $second->getDocumentHash()), $consent->getPdfHash());

	// žádost je vyřízená, tablet už nic nezobrazuje; uložilo se to jedním flush
	Assert::equal($now, $request->getSignedAt());
	Assert::same(TabletSignRequestState::Signed, $request->getState($now));
	Assert::null($service->getOpenRequest($request->getTablet()));
	Assert::same(['evidence', 'evidence', 'pdf contract', 'pdf consent', 'signed 2/2'], $handler->calls);
});


test('PDF žádosti se generuje jednou - náhled, otisk i vložení podpisů pracují se stejnými bajty', function () use ($now) {
	$storage = new \Nette\Caching\Storages\MemoryStorage();
	[$signing, , $request, $handler] = signing($now, storage: $storage);
	(new \ReflectionProperty($request, 'id'))->setValue($request, 7);

	// stránka podpisu (místa), náhled a odeslání - každé jako samostatný požadavek
	$page = $signing->getDocuments($request)['contract'];
	$signing->getPlaces('contract', $page);
	$handler->documents = $handler->freshDocuments();
	$preview = $signing->getDocuments($request)['contract']->getPdf();
	$handler->documents = $handler->freshDocuments();
	$documents = $signing->sign($request, submission());

	Assert::same(1, $handler->generated);
	Assert::same(hash('sha256', $preview), $documents[0]->getOriginalPdfHash());

	// po podpisu mezipaměť žádosti zmizí
	Assert::null($storage->read('fancyadmin.tabletSign' . \Nette\Caching\Cache::NamespaceSeparator . 'pdf-7-contract'));
});


test('bez mezipaměti se PDF generuje při každém požadavku', function () use ($now) {
	[$signing, , $request, $handler] = signing($now);
	$signing->getDocuments($request)['contract']->getPdf();
	$handler->documents = $handler->freshDocuments(); // další požadavek
	$signing->getDocuments($request)['contract']->getPdf();

	Assert::same(2, $handler->generated);
});


test('každé místo musí být podepsané a jen místy, která dokumenty mají', function () use ($now) {
	[$signing, , $request, , $em] = signing($now);
	$flushes = $em->flushCount;

	// chybí druhé místo smlouvy
	Assert::exception(fn () => $signing->sign($request, submission(['contract:0' => 's1', 'consent:-' => 's2'])), TabletSignIncompleteException::class);
	// podpis místa, které dokument nemá (poradce)
	Assert::exception(fn () => $signing->sign($request, submission(['contract:0' => 's1', 'contract:1' => 's1', 'contract:2' => 's1', 'consent:-' => 's2'])), TabletSignIncompleteException::class);
	// místo odkazuje na podpis, který stránka neposlala
	Assert::exception(fn () => $signing->sign($request, submission(['contract:0' => 's1', 'contract:2' => 's9', 'consent:-' => 's2'])), TabletSignIncompleteException::class);

	Assert::same($flushes, $em->flushCount);
	Assert::null($request->getSignedAt());
});


test('když PDF nevznikne, nic se neuloží, žádost zůstane otevřená a jde podepsat znovu', function () use ($now) {
	[$signing, $service, $request, $handler, $em] = signing($now);
	$flushes = $em->flushCount;
	$handler->pdfFailure = new \RuntimeException('Generátor PDF spadl.');

	Assert::exception(fn () => $signing->sign($request, submission()), \RuntimeException::class, 'Generátor PDF spadl.');
	Assert::same($flushes, $em->flushCount);
	// projekt mohl v createSignedPdf() sám flushovat - transakce to vrátí
	Assert::same(['begin', 'rollBack'], $em->getConnection()->transactions);
	Assert::null($request->getSignedAt());
	Assert::same($request, $service->getOpenRequest($request->getTablet()));

	$handler->pdfFailure = null;
	$signing->sign($request, submission());
	Assert::equal($now, $request->getSignedAt());
	Assert::same(['begin', 'rollBack', 'begin', 'commit'], $em->getConnection()->transactions);
});


test('podpis jednoho podepisujícího nejde vložit do místa jiného', function () use ($now) {
	[$signing, , $request, $handler] = signing($now);
	// souhlas podepisuje zákonný zástupce - podpis klienta ze smlouvy do něj patřit nesmí
	$handler->documents[1] = new TabletSignDocument('Souhlas zástupce', text: 'Souhlasím …', key: 'consent', signerName: 'Eva Nováková');

	Assert::exception(fn () => $signing->sign($request, submission(['contract:0' => 's1', 'contract:2' => 's1', 'consent:-' => 's1'])), TabletSignIncompleteException::class, '~různých podepisujících~');
	Assert::null($request->getSignedAt());

	// vlastní podpis zástupce projde
	$signing->sign($request, submission(['contract:0' => 's1', 'contract:2' => 's1', 'consent:-' => 's2']));
	Assert::equal($now, $request->getSignedAt());
});


test('podepsat jde jen žádost, kterou tablet právě zobrazuje', function () use ($now) {
	[$signing, $service, $request] = signing($now);
	$service->release($request->getTablet(), $now);

	Assert::exception(fn () => $signing->sign($request, submission()), \LogicException::class);
});


test('dokument, který nejde podepsat, se nepodepíše', function () use ($now) {
	[$signing, , $request, $handler] = signing($now);
	$handler->documents[] = new TabletSignDocument('Dodatek', key: 'addendum', error: 'Dodatek už je podepsaný.');

	Assert::exception(fn () => $signing->sign($request, submission()), \LogicException::class, '~Dodatek už je podepsaný~');
});


test('obsah stránky se kontroluje - podvržený podpis neprojde', function () {
	$submission = submission();
	Assert::same(['s1', 's2', 's3'], array_keys($submission->signatures));
	Assert::type(SignatureCapture::class, $submission->signatures['s1']);

	Assert::exception(fn () => TabletSignSubmission::fromJson('{'), InvalidSignatureException::class);
	Assert::exception(fn () => TabletSignSubmission::fromJson('{"signatures": [], "placements": "x"}'), InvalidSignatureException::class);
	Assert::exception(fn () => TabletSignSubmission::fromJson(json_encode([
		'signatures' => ['s1' => ['image' => 'data:image/png;base64,AAAA', 'biometrics' => '{}']],
		'placements' => [],
	])), InvalidSignatureException::class);
});


test('bez handleru projektu nebo entit je podpis vypnutý', function () use ($now) {
	[$withoutHandler, , $request] = signing($now, withHandler: false);
	Assert::false($withoutHandler->isEnabled());
	Assert::exception(fn () => $withoutHandler->getDocuments($request), \LogicException::class);

	[$withoutDocument] = signing($now, documentClass: null);
	Assert::false($withoutDocument->isEnabled());

	[$withoutSignature] = signing($now, signatureClass: null);
	Assert::false($withoutSignature->isEnabled());
});


test('mapování: podepsaný dokument s vazbou na žádost a soubor, podpis s biometrikou a místy', function () {
	$metadata = new ClassMetadata(SignedDocument::class);
	$metadata->initializeReflection(new RuntimeReflectionService());
	(new AttributeDriver([]))->loadMetadataForClass(SignedDocument::class, $metadata);

	$fields = $metadata->getFieldNames();
	foreach (['id', 'documentKey', 'title', 'type', 'subjectId', 'data', 'createdAt', 'originalPdfHash', 'pdfHash'] as $field) {
		Assert::contains($field, $fields);
	}
	Assert::notContains('biometricsJson', $fields);

	$request = $metadata->getAssociationMapping('request');
	Assert::same(TabletSignRequest::class, $request->targetEntity);
	Assert::same('SET NULL', $request->joinColumns[0]->onDelete);

	$file = $metadata->getAssociationMapping('file');
	Assert::same(File::class, $file->targetEntity);
	Assert::true($file->joinColumns[0]->nullable);

	$metadata = new ClassMetadata(DocumentSignature::class);
	$metadata->initializeReflection(new RuntimeReflectionService());
	(new AttributeDriver([]))->loadMetadataForClass(DocumentSignature::class, $metadata);

	$fields = $metadata->getFieldNames();
	foreach (['id', 'placements', 'createdAt', 'imageDataUri', 'biometricsJson', 'onSite', 'verifiedAt', 'evidenceJson', 'documentHash'] as $field) {
		Assert::contains($field, $fields);
	}

	$request = $metadata->getAssociationMapping('request');
	Assert::same(TabletSignRequest::class, $request->targetEntity);
	Assert::same('SET NULL', $request->joinColumns[0]->onDelete);
});
