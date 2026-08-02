<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface RequestLogBody extends Entity
{
	public function getRequestLog(): RequestLog;
	public function setRequestLog(RequestLog $requestLog): static;
	public function getHeaders(): ?array;
	public function setHeaders(?array $headers): static;
	public function getParams(): ?array;
	public function setParams(?array $params): static;
	public function getPostData(): ?string;
	public function setPostData(?string $postData): static;
	public function getRawDataJson(): ?array;
	public function setRawDataJson(?array $rawDataJson): static;
	public function getRawDataText(): ?string;
	public function setRawDataText(?string $rawDataText): static;
	public function getResponseJson(): ?array;
	public function setResponseJson(?array $responseJson): static;
	public function getResponseText(): ?string;
	public function setResponseText(?string $responseText): static;
}
