<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use DateTimeInterface;
use Nette\Http\IResponse;

/**
 * Zaznamenava vse, co do odpovedi odejde, misto aby to poslalo do PHP - testy si tak
 * muzou sahnout na parametry cookie (path, domena, expirace) presne tak, jak je kod nastavil.
 */
final class TestHttpResponse implements IResponse
{
	/** @var list<array{name: string, value: string, expire: string|int|DateTimeInterface|null, path: ?string, domain: ?string, secure: ?bool, httpOnly: ?bool}> */
	public array $cookies = [];

	/** @var list<array{name: string, path: ?string, domain: ?string, secure: ?bool}> */
	public array $deletedCookies = [];

	/** @var array<string, string> */
	public array $headers = [];

	public int $code = self::S200_OK;

	public ?string $redirectedTo = null;

	public bool $sent = false;

	public function setCode(int $code, ?string $reason = null): static
	{
		$this->code = $code;
		return $this;
	}

	public function getCode(): int
	{
		return $this->code;
	}

	public function setHeader(string $name, string $value): static
	{
		$this->headers[$name] = $value;
		return $this;
	}

	public function addHeader(string $name, string $value): static
	{
		$this->headers[$name] = isset($this->headers[$name])
			? $this->headers[$name] . ', ' . $value
			: $value;
		return $this;
	}

	public function setContentType(string $type, ?string $charset = null): static
	{
		return $this->setHeader('Content-Type', $type . ($charset ? '; charset=' . $charset : ''));
	}

	public function redirect(string $url, int $code = self::S302_Found): void
	{
		$this->redirectedTo = $url;
		$this->code = $code;
	}

	public function setExpiration(?string $expire): static
	{
		return $this;
	}

	public function isSent(): bool
	{
		return $this->sent;
	}

	public function getHeader(string $header): ?string
	{
		return $this->headers[$header] ?? null;
	}

	public function getHeaders(): array
	{
		return $this->headers;
	}

	public function setCookie(
		string $name,
		string $value,
		string|int|DateTimeInterface|null $expire,
		?string $path = null,
		?string $domain = null,
		?bool $secure = null,
		?bool $httpOnly = null,
	): static
	{
		$this->cookies[] = [
			'name' => $name,
			'value' => $value,
			'expire' => $expire,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
			'httpOnly' => $httpOnly,
		];
		return $this;
	}

	public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
	{
		$this->deletedCookies[] = [
			'name' => $name,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
		];
	}

	/** @return array{name: string, value: string, expire: string|int|DateTimeInterface|null, path: ?string, domain: ?string, secure: ?bool, httpOnly: ?bool}|null */
	public function getLastCookie(): ?array
	{
		return $this->cookies ? $this->cookies[array_key_last($this->cookies)] : null;
	}
}
