<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

trait RequestLogTrait
{
	/**
	 * UTC, s milisekundami. Sub-sekundy jsou potreba k serazeni requestu
	 * v ramci jedne sekundy - bez nich se pri dohledavani incidentu neda
	 * rict, co probehlo driv. Plain DATETIME by je tise zahodil.
	 */
	#[ORM\Column(columnDefinition: 'DATETIME(3) NOT NULL')]
	protected DateTimeImmutable $createdAt;

	#[ORM\Column(type: 'integer', nullable: true)]
	protected ?int $identityId = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	protected ?int $apiKeyId = null;

	#[ORM\Column(type: 'text')]
	protected string $url;

	#[ORM\Column(length: 7)]
	protected string $method;

	#[ORM\Column]
	protected int $code;

	// 45 = maximum pro IPv6 (vcetne IPv4-mapped tvaru). 15 by staclo jen na
	// IPv4 a prvni IPv6 klient by shodil insert.
	#[ORM\Column(length: 45)]
	protected string $ip;

	// scale 4 = rozliseni 0.1 ms; se scale 2 byly vsechny requesty pod 10 ms
	// nerozlisitelne (0.00 vs 0.01)
	#[Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
	protected ?string $responseTime = null;

	/**
	 * Identifikator operace, kterou request nesl - stejna hodnota, jakou ma
	 * audit_log.correlation_id. Mustek mezi auditni a provozni vrstvou:
	 * z auditni udalosti se jednim dotazem dohleda request vcetne payloadu.
	 *
	 * Netypovany zamerne: request_log je genericky a nevi dopredu, jake typy
	 * operaci ponese. Plni se pres RequestLogger::addValue('correlation_id', ...)
	 * jen tam, kde request operaci nese; jinak zustava NULL.
	 *
	 * POZOR: index si musi deklarovat konzumujici entita - Doctrine atributy
	 * #[Index] na traitech ignoruje.
	 */
	#[ORM\Column(nullable: true)]
	protected ?string $correlationId = null;

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	public function getIdentityId(): ?int
	{
		return $this->identityId;
	}

	public function setIdentityId(?int $identityId): static
	{
		$this->identityId = $identityId;
		return $this;
	}

	public function getApiKeyId(): ?int
	{
		return $this->apiKeyId;
	}

	public function setApiKeyId(?int $apiKeyId): static
	{
		$this->apiKeyId = $apiKeyId;
		return $this;
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	public function setUrl(string $url): static
	{
		$this->url = $url;
		return $this;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function setMethod(string $method): static
	{
		$this->method = $method;
		return $this;
	}

	public function getCode(): int
	{
		return $this->code;
	}

	public function setCode(int $code): static
	{
		$this->code = $code;
		return $this;
	}

	public function getIp(): string
	{
		return $this->ip;
	}

	public function setIp(string $ip): static
	{
		$this->ip = $ip;
		return $this;
	}

	public function getResponseTime(): ?float
	{
		return $this->responseTime !== null ? round((float) $this->responseTime, 4) : null;
	}

	public function setResponseTime(?float $responseTime): static
	{
		$this->responseTime = $responseTime !== null ? (string) round($responseTime, 4) : null;
		return $this;
	}

	public function getCorrelationId(): ?string
	{
		return $this->correlationId;
	}

	public function setCorrelationId(?string $correlationId): static
	{
		$this->correlationId = $correlationId;
		return $this;
	}
}
