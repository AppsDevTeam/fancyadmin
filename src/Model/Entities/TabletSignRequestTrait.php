<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce žádosti o podpis poslané na tablet (rozhraní TabletSignRequest).
 * Projektová entita: #[ORM\Entity] #[ORM\Table(name: 'tablet_sign_request')] + implements
 * TabletSignRequest + use TabletSignRequestTrait, konstruktor bez povinných parametrů
 * (žádost vytváří create()). Entita Tablet projektu musí být ve stejném jmenném prostoru.
 */
trait TabletSignRequestTrait
{
	use CreatedAt;

	/** Smazaný tablet evidenci nemaže - zůstane název */
	#[ORM\ManyToOne(targetEntity: 'Tablet')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?Tablet $tablet = null;

	#[ORM\Column(length: 100)]
	protected string $tabletName;

	/** Odkaz, který tablet otevře (jednorázový odkaz projektu) */
	#[ORM\Column(type: 'text')]
	#[Anonymize(AnonymizationType::SECRET)]
	protected string $url;

	#[ORM\Column(length: 255)]
	protected string $title;

	/** Druh podepisovaného dokumentu - kód projektu */
	#[ORM\Column(length: 100, nullable: true)]
	protected ?string $type = null;

	/** ID záznamu projektu, kterého se podpis týká */
	#[ORM\Column(length: 64, nullable: true)]
	protected ?string $subjectId = null;

	/** @var array<mixed>|null další údaje projektu */
	#[ORM\Column(type: 'json', nullable: true)]
	#[Anonymize(AnonymizationType::FREE_TEXT)]
	protected ?array $data = null;

	#[ORM\Column(length: 64, nullable: true)]
	protected ?string $requesterId = null;

	#[ORM\Column(length: 255, nullable: true)]
	#[Anonymize(AnonymizationType::FULL_NAME)]
	protected ?string $requesterName = null;

	/** Do kdy si žádost tablet může vyzvednout */
	#[ORM\Column]
	protected \DateTimeImmutable $expiresAt;

	/** Kdy si žádost tablet vyzvedl a otevřel */
	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $openedAt = null;

	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $signedAt = null;

	/** Zrušeno: tablet se vrátil na čekací stránku, byl odpojen nebo dostal jinou žádost */
	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $cancelledAt = null;

	public static function create(Tablet $tablet, string $url, string $title, \DateTimeImmutable $now, \DateTimeImmutable $expiresAt): static
	{
		$request = new static();
		$request->tablet = $tablet;
		$request->tabletName = $tablet->getName();
		$request->url = $url;
		$request->title = mb_substr($title, 0, 255);
		$request->expiresAt = $expiresAt;
		$request->setCreatedAt($now);

		return $request;
	}

	public function getTablet(): ?Tablet
	{
		return $this->tablet;
	}

	public function getTabletName(): string
	{
		return $this->tabletName;
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function getSubjectId(): ?string
	{
		return $this->subjectId;
	}

	public function getData(): ?array
	{
		return $this->data;
	}

	public function setSubject(?string $type, ?string $subjectId, ?array $data = null): static
	{
		$this->type = $type;
		$this->subjectId = $subjectId;
		$this->data = $data;

		return $this;
	}

	public function getRequesterId(): ?string
	{
		return $this->requesterId;
	}

	public function getRequesterName(): ?string
	{
		return $this->requesterName;
	}

	public function setRequester(?string $id, ?string $name): static
	{
		$this->requesterId = $id;
		$this->requesterName = $name === null ? null : mb_substr($name, 0, 255);

		return $this;
	}

	public function getExpiresAt(): \DateTimeImmutable
	{
		return $this->expiresAt;
	}

	public function getOpenedAt(): ?\DateTimeImmutable
	{
		return $this->openedAt;
	}

	public function getSignedAt(): ?\DateTimeImmutable
	{
		return $this->signedAt;
	}

	public function getCancelledAt(): ?\DateTimeImmutable
	{
		return $this->cancelledAt;
	}

	public function markOpened(\DateTimeImmutable $at): static
	{
		$this->openedAt = $at;

		return $this;
	}

	public function markSigned(\DateTimeImmutable $at): static
	{
		$this->signedAt = $at;

		return $this;
	}

	public function cancel(\DateTimeImmutable $at): static
	{
		$this->cancelledAt = $at;

		return $this;
	}

	public function isPending(\DateTimeImmutable $now): bool
	{
		return $this->getState($now) === TabletSignRequestState::Pending;
	}

	public function isOpen(): bool
	{
		return $this->openedAt !== null && $this->signedAt === null && $this->cancelledAt === null;
	}

	public function getState(\DateTimeImmutable $now): TabletSignRequestState
	{
		return match (true) {
			$this->signedAt !== null => TabletSignRequestState::Signed,
			$this->cancelledAt !== null => TabletSignRequestState::Cancelled,
			$this->openedAt !== null => TabletSignRequestState::Opened,
			$this->expiresAt <= $now => TabletSignRequestState::Expired,
			default => TabletSignRequestState::Pending,
		};
	}
}
