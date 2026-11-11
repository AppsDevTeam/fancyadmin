<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce tabletu (rozhraní Tablet). V DB jsou jen SHA-256 hashe párovacího tokenu a klíče
 * zařízení, adresa odběru i čekající odkaz jsou tajné (anonymizace je přepíše).
 * Projektová entita: #[ORM\Entity] #[ORM\Table(name: 'tablet')] + implements Tablet + use TabletTrait.
 */
trait TabletTrait
{
	use CreatedAt;

	/** Název tabletu (např. "Tablet recepce") - recepce ho vybírá při odeslání formuláře */
	#[ORM\Column(length: 100, unique: true)]
	protected string $name;

	#[ORM\Column(length: 64, unique: true, nullable: true)]
	#[Anonymize(AnonymizationType::SECRET)]
	protected ?string $pairingTokenHash = null;

	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $pairingExpiresAt = null;

	#[ORM\Column(length: 64, unique: true, nullable: true)]
	#[Anonymize(AnonymizationType::SECRET)]
	protected ?string $deviceSecretHash = null;

	/** Adresa odběru Web Push prohlížeče tabletu (null = notifikace nepovolené) */
	#[ORM\Column(type: 'text', nullable: true)]
	#[Anonymize(AnonymizationType::SECRET)]
	protected ?string $pushEndpoint = null;

	/** Formulář čekající na vyzvednutí service workerem (jednorázový odkaz projektu) */
	#[ORM\Column(type: 'text', nullable: true)]
	#[Anonymize(AnonymizationType::SECRET)]
	protected ?string $pendingFormUrl = null;

	#[ORM\Column(length: 255, nullable: true)]
	protected ?string $pendingFormTitle = null;

	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $pendingFormExpiresAt = null;

	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $pairedAt = null;

	/** Kdy tablet naposledy otevřel čekací stránku nebo se z ní zeptal na formulář (dotazování) */
	#[ORM\Column(nullable: true)]
	protected ?\DateTimeImmutable $lastSeenAt = null;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getPairingTokenHash(): ?string
	{
		return $this->pairingTokenHash;
	}

	public function getPairingExpiresAt(): ?\DateTimeImmutable
	{
		return $this->pairingExpiresAt;
	}

	public function setPairingToken(?string $hash, ?\DateTimeImmutable $expiresAt): static
	{
		$this->pairingTokenHash = $hash;
		$this->pairingExpiresAt = $expiresAt;
		return $this;
	}

	public function getDeviceSecretHash(): ?string
	{
		return $this->deviceSecretHash;
	}

	/** Spárování s novým zařízením - odběr notifikací předchozího zařízení už neplatí */
	public function pair(string $deviceSecretHash, \DateTimeImmutable $at): static
	{
		$this->deviceSecretHash = $deviceSecretHash;
		$this->pairedAt = $at;
		$this->pushEndpoint = null;
		$this->setPendingForm(null, null, null);
		return $this->setPairingToken(null, null);
	}

	public function disconnect(): static
	{
		$this->deviceSecretHash = null;
		$this->pairedAt = null;
		$this->pushEndpoint = null;
		$this->setPendingForm(null, null, null);
		return $this->setPairingToken(null, null);
	}

	public function isPaired(): bool
	{
		return $this->deviceSecretHash !== null;
	}

	public function getPushEndpoint(): ?string
	{
		return $this->pushEndpoint;
	}

	public function setPushEndpoint(?string $pushEndpoint): static
	{
		$this->pushEndpoint = $pushEndpoint;
		return $this;
	}

	public function setPendingForm(?string $url, ?string $title, ?\DateTimeImmutable $expiresAt): static
	{
		$this->pendingFormUrl = $url;
		$this->pendingFormTitle = $title;
		$this->pendingFormExpiresAt = $expiresAt;
		return $this;
	}

	public function getPendingFormUrl(): ?string
	{
		return $this->pendingFormUrl;
	}

	public function getPendingFormTitle(): ?string
	{
		return $this->pendingFormTitle;
	}

	public function getPendingFormExpiresAt(): ?\DateTimeImmutable
	{
		return $this->pendingFormExpiresAt;
	}

	/**
	 * Spárovaný tablet s povolenými notifikacemi - formulář ho probudí push zprávou. Bez
	 * notifikací jde formulář poslat jen s dotazováním (fancyadmin: tablet: pollInterval)
	 * a dorazí jen na otevřenou čekací stránku, viz TabletService::sendForm().
	 */
	public function canReceiveForms(): bool
	{
		return $this->isPaired() && $this->pushEndpoint !== null;
	}

	public function getPairedAt(): ?\DateTimeImmutable
	{
		return $this->pairedAt;
	}

	public function getLastSeenAt(): ?\DateTimeImmutable
	{
		return $this->lastSeenAt;
	}

	public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static
	{
		$this->lastSeenAt = $lastSeenAt;
		return $this;
	}
}
