<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\TabletSigningEntities;

use ADT\BiometricSignature\Biometrics\BiometricsNormalizer;
use ADT\BiometricSignature\BiometricSigning;
use ADT\BiometricSignature\Evidence\EvidenceVerifier;
use ADT\BiometricSignature\SignatureCapture;
use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
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
 * Podpis dokumentu na tabletu (TabletSigning, docs/tablets.md): k otevřené žádosti uloží
 * biometrický podpis jako podepsaný dokument, vytvoří důkazní záznam a podepsané PDF a žádost
 * vyřídí. Projekt (TabletSignHandler) dodává jen to, co se podepisuje, a PDF.
 */

require __DIR__ . '/bootstrap.php';

if (!class_exists(BiometricSigning::class)) {
	Environment::skip('Podpis na tabletu potřebuje volitelný balíček adt/biometric-signature.');
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

	public ?\Throwable $pdfFailure = null;

	public function getDocument(TabletSignRequestInterface $request): ?TabletSignDocument
	{
		return new TabletSignDocument($request->getTitle(), fn (): string => '%PDF-original');
	}

	public function getEvidenceSubject(SignedDocumentInterface $document): array
	{
		$this->calls[] = 'evidence';

		return ['client' => ['id' => 17, 'name' => 'Jan Novák']];
	}

	public function createSignedPdf(SignedDocumentInterface $document): ?SignedPdf
	{
		$this->calls[] = 'pdf';
		if ($this->pdfFailure !== null) {
			throw $this->pdfFailure;
		}

		// otisk důkazního záznamu už je známý - tiskne se do PDF
		$content = '%PDF-signed ' . $document->getDocumentHash();

		return new SignedPdf((new File())->setTemporaryContent($content, 'smlouva.pdf'), $content);
	}

	public function onSigned(SignedDocumentInterface $document): void
	{
		$this->calls[] = 'signed';
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


function capture(): SignatureCapture
{
	return SignatureCapture::fromInput(
		'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
		json_encode([
			'version' => 1,
			'startedAt' => '2026-01-01T11:59:00.000Z',
			'canvas' => ['width' => 600, 'height' => 200, 'devicePixelRatio' => 2],
			'device' => ['userAgent' => 'Mozilla/5.0 (iPad)', 'maxTouchPoints' => 5, 'screen' => ['width' => 1024, 'height' => 1366]],
			'fields' => BiometricsNormalizer::FIELDS,
			'strokes' => [['type' => 'pen', 'points' => [
				[10.5, 20.25, 0, 0.31, 12, -4, 1.1, 0.5, 0, 1, 1],
				[11.75, 21, 4.17, 0.42, 13, -3, 1.08, 0.52, 0, 1, 1],
				[13, 23.5, 8.33, 0.45, 13, -3, 1.07, 0.53, 0, 1, 1],
			]]],
		], JSON_THROW_ON_ERROR),
	);
}

/** @return array{TabletSigning, TabletService, TabletSignRequest, RecordingSignHandler} */
function signing(DateTimeImmutable $now, ?string $documentClass = SignedDocument::class, bool $withHandler = true): array
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
		'Smlouva o poskytování služeb',
		fn (TabletSignRequestInterface $request) => $request->setSubject('order', '4321', ['client' => 17])->setRequester('5', 'Jana Nováková'),
		$now->modify('-2 minutes'),
	)->request;
	$service->takePendingForm($tablet, $now->modify('-1 minute'));

	$handler = new RecordingSignHandler();

	return [new TabletSigning($em, $service, $withHandler ? $handler : null, $documentClass, new FixedClock($now)), $service, $request, $handler];
}

$now = new DateTimeImmutable('2026-01-01 12:00:00');


test('podpis uloží podepsaný dokument s důkazním záznamem a PDF a žádost vyřídí', function () use ($now) {
	[$signing, $service, $request, $handler] = signing($now);
	Assert::true($signing->isEnabled());
	Assert::same('Smlouva o poskytování služeb', $signing->getDocument($request)->title);

	$document = $signing->sign($request, capture(), '192.0.2.10', 'Mozilla/5.0 (iPad)');

	// podepsaný dokument nese, čeho se týká - obecně, převzaté ze žádosti
	Assert::type(SignedDocument::class, $document);
	Assert::same($request, $document->getRequest());
	Assert::same('Smlouva o poskytování služeb', $document->getTitle());
	Assert::same('order', $document->getType());
	Assert::same('4321', $document->getSubjectId());
	Assert::same(['client' => 17], $document->getData());
	Assert::equal($now, $document->getCreatedAt());

	// podpis na tabletu provozovatele: bez kódu, ověřený rovnou
	Assert::true($document->isOnSite());
	Assert::true($document->isVerified());
	Assert::equal($now, $document->getVerifiedAt());
	Assert::same('', $document->getPhone());
	Assert::same('192.0.2.10', $document->getIpAddress());

	// důkazní záznam: co se podepsalo, komu to obsluha poslala a co dodal projekt
	$evidence = json_decode($document->getEvidenceJson(), true, flags: JSON_THROW_ON_ERROR);
	Assert::true($evidence['onSite']);
	Assert::null($evidence['codeVerification']);
	Assert::same(['title' => 'Smlouva o poskytování služeb', 'type' => 'order', 'subjectId' => '4321', 'data' => ['client' => 17]], $evidence['subject']['document']);
	Assert::same('Recepce', $evidence['subject']['tablet']['name']);
	Assert::same('Jana Nováková', $evidence['subject']['tablet']['requesterName']);
	Assert::same(['client' => ['id' => 17, 'name' => 'Jan Novák']], $evidence['subject']['subject']);
	Assert::same(hash('sha256', $document->getEvidenceJson()), $document->getDocumentHash());

	// podepsané PDF od projektu: soubor a otisk obsahu
	$pdf = '%PDF-signed ' . $document->getDocumentHash();
	Assert::type(File::class, $document->getFile());
	Assert::same($pdf, $document->getFile()->getTemporaryContent());
	Assert::same(hash('sha256', $pdf), $document->getPdfHash());
	Assert::true((new EvidenceVerifier())->verify($document, $pdf)->isValid());

	// žádost je vyřízená, tablet už nic nezobrazuje
	Assert::equal($now, $request->getSignedAt());
	Assert::same(TabletSignRequestState::Signed, $request->getState($now));
	Assert::null($service->getOpenRequest($request->getTablet()));
	Assert::same(['evidence', 'pdf', 'signed'], $handler->calls);
});


test('když PDF nevznikne, podpis zůstane neověřený, žádost otevřená a jde podepsat znovu', function () use ($now) {
	[$signing, $service, $request, $handler] = signing($now);
	$handler->pdfFailure = new \RuntimeException('Generátor PDF spadl.');

	Assert::exception(fn () => $signing->sign($request, capture()), \RuntimeException::class, 'Generátor PDF spadl.');
	Assert::null($request->getSignedAt());
	Assert::same($request, $service->getOpenRequest($request->getTablet()));
	Assert::same(['evidence', 'pdf'], $handler->calls);

	$handler->pdfFailure = null;
	$document = $signing->sign($request, capture());
	Assert::true($document->isVerified());
	Assert::equal($now, $request->getSignedAt());
});


test('podepsat jde jen žádost, kterou tablet právě zobrazuje', function () use ($now) {
	[$signing, $service, $request] = signing($now);
	$service->release($request->getTablet(), $now);

	Assert::exception(fn () => $signing->sign($request, capture()), \LogicException::class);
});


test('bez handleru projektu nebo entity podepsaného dokumentu je podpis vypnutý', function () use ($now) {
	[$withoutHandler, , $request] = signing($now, withHandler: false);
	Assert::false($withoutHandler->isEnabled());
	Assert::exception(fn () => $withoutHandler->getDocument($request), \LogicException::class);

	[$withoutEntity] = signing($now, documentClass: null);
	Assert::false($withoutEntity->isEnabled());
});


test('mapování podepsaného dokumentu: podpis s biometrikou, vazba na žádost a soubor', function () {
	$metadata = new ClassMetadata(SignedDocument::class);
	$metadata->initializeReflection(new RuntimeReflectionService());
	(new AttributeDriver([]))->loadMetadataForClass(SignedDocument::class, $metadata);

	$fields = $metadata->getFieldNames();
	foreach (['id', 'title', 'type', 'subjectId', 'data', 'createdAt', 'imageDataUri', 'biometricsJson', 'onSite', 'verifiedAt', 'evidenceJson', 'documentHash', 'pdfHash'] as $field) {
		Assert::contains($field, $fields);
	}

	$request = $metadata->getAssociationMapping('request');
	Assert::same(TabletSignRequest::class, $request->targetEntity);
	Assert::same('SET NULL', $request->joinColumns[0]->onDelete);

	$file = $metadata->getAssociationMapping('file');
	Assert::same(File::class, $file->targetEntity);
	Assert::true($file->joinColumns[0]->nullable);
});
