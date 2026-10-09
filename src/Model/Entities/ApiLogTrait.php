<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Záznam o volání cizího rozhraní, kde aplikace vystupuje jako klient.
 *
 * Opak RequestLogu: ten drží, co přišlo zvenčí, tenhle to, co aplikace sama poslala ven.
 * Kdo je protistrana a co se volalo, si určuje projekt - `type` proto v traitě není,
 * každá aplikace má vlastní výčet rozhraní a chce ho mít otypovaný svým enumem.
 *
 * Reference jsou prostá čísla bez cizích klíčů: log nesmí bránit smazání toho, o čem
 * vypovídá, a odváží se do úložiště, kde cílová tabulka stejně neexistuje.
 */
trait ApiLogTrait
{
	#[ORM\Column]
	protected DateTimeImmutable $createdAt;

	#[ORM\Column(nullable: true)]
	protected ?int $accountId = null;

	/** ostrý provoz vs. testovací prostředí protistrany - bez toho nejde záznam vyložit */
	#[ORM\Column(nullable: true)]
	protected ?string $environment = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	protected ?string $endpointUrl = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	protected ?string $request = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	protected ?string $response = null;

	#[ORM\Column(nullable: true)]
	protected ?int $httpStatusCode = null;

	/** chyba spojení (timeout, DNS) - plní se jen když protistrana neodpověděla */
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	protected ?string $errorMessage = null;

	#[ORM\Column(nullable: true)]
	protected ?int $durationMs = null;

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	public function getAccountId(): ?int
	{
		return $this->accountId;
	}

	public function setAccountId(?int $accountId): static
	{
		$this->accountId = $accountId;
		return $this;
	}

	public function getEnvironment(): ?string
	{
		return $this->environment;
	}

	public function setEnvironment(?string $environment): static
	{
		$this->environment = $environment;
		return $this;
	}

	public function getEndpointUrl(): ?string
	{
		return $this->endpointUrl;
	}

	public function setEndpointUrl(?string $endpointUrl): static
	{
		$this->endpointUrl = $endpointUrl;
		return $this;
	}

	public function getRequest(): ?string
	{
		return $this->request;
	}

	public function setRequest(?string $request): static
	{
		$this->request = $request;
		return $this;
	}

	public function getResponse(): ?string
	{
		return $this->response;
	}

	public function setResponse(?string $response): static
	{
		$this->response = $response;
		return $this;
	}

	public function getHttpStatusCode(): ?int
	{
		return $this->httpStatusCode;
	}

	public function setHttpStatusCode(?int $httpStatusCode): static
	{
		$this->httpStatusCode = $httpStatusCode;
		return $this;
	}

	public function getErrorMessage(): ?string
	{
		return $this->errorMessage;
	}

	public function setErrorMessage(?string $errorMessage): static
	{
		$this->errorMessage = $errorMessage;
		return $this;
	}

	public function getDurationMs(): ?int
	{
		return $this->durationMs;
	}

	public function setDurationMs(?int $durationMs): static
	{
		$this->durationMs = $durationMs;
		return $this;
	}
}
