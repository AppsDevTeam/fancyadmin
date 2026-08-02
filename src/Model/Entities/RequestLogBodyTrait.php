<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

trait RequestLogBodyTrait
{
	#[ORM\OneToOne(targetEntity: 'RequestLog')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected RequestLog $requestLog;

	#[Column(type: 'json', nullable: true)]
	protected ?array $headers = null;

	#[Column(type: 'json', nullable: true)]
	protected ?array $params = null;

	#[Column(type: 'text', nullable: true)]
	protected ?string $postData = null;

	#[Column(type: 'json', nullable: true)]
	protected ?array $rawDataJson = null;

	#[Column(type: 'text', nullable: true)]
	protected ?string $rawDataText = null;

	#[Column(type: 'json', nullable: true)]
	protected ?array $responseJson = null;

	#[Column(type: 'text', nullable: true)]
	protected ?string $responseText = null;

	public function getRequestLog(): RequestLog
	{
		return $this->requestLog;
	}

	public function setRequestLog(RequestLog $requestLog): static
	{
		$this->requestLog = $requestLog;
		return $this;
	}

	public function getHeaders(): ?array
	{
		return $this->headers;
	}

	public function setHeaders(?array $headers): static
	{
		$this->headers = $headers;
		return $this;
	}

	public function getParams(): ?array
	{
		return $this->params;
	}

	public function setParams(?array $params): static
	{
		$this->params = $params;
		return $this;
	}

	public function getPostData(): ?string
	{
		return $this->postData;
	}

	public function setPostData(?string $postData): static
	{
		$this->postData = $postData;
		return $this;
	}

	public function getRawDataJson(): ?array
	{
		return $this->rawDataJson;
	}

	public function setRawDataJson(?array $rawDataJson): static
	{
		$this->rawDataJson = $rawDataJson;
		return $this;
	}

	public function getRawDataText(): ?string
	{
		return $this->rawDataText;
	}

	public function setRawDataText(?string $rawDataText): static
	{
		$this->rawDataText = $rawDataText;
		return $this;
	}

	public function getResponseJson(): ?array
	{
		return $this->responseJson;
	}

	public function setResponseJson(?array $responseJson): static
	{
		$this->responseJson = $responseJson;
		return $this;
	}

	public function getResponseText(): ?string
	{
		return $this->responseText;
	}

	public function setResponseText(?string $responseText): static
	{
		$this->responseText = $responseText;
		return $this;
	}
}
