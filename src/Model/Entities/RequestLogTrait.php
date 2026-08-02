<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

trait RequestLogTrait
{
	#[ORM\Column]
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

	#[ORM\Column(length: 15)]
	protected string $ip;

	#[Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
	protected ?string $responseTime = null;

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
		return $this->responseTime !== null ? round((float) $this->responseTime, 2) : null;
	}

	public function setResponseTime(?float $responseTime): static
	{
		$this->responseTime = $responseTime !== null ? (string) round($responseTime, 2) : null;
		return $this;
	}
}
