<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\ReturnPath;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * Cil navratu po prihlaseni si nesmi pamatovat signal.
 *
 * Odkaz na signal nese stav sezeni, ve kterem vznikl - poradi v gridu, CSRF token - takze
 * po prihlaseni uz neplati a uzivatel by misto cile dostal chybovou stranku. Tyka se to
 * i razeni, strankovani a exportu v gridech, ne jen akci.
 */

require __DIR__ . '/bootstrap.php';


class ResponseSpy implements IResponse
{
	public ?string $cookie = null;

	public function setCookie(string $name, string $value, string|int|DateTimeInterface|null $expire, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null, ?string $sameSite = null): static
	{
		$this->cookie = $value;
		return $this;
	}

	public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void {}
	public function setCode(int $code, ?string $reason = null): static { return $this; }
	public function getCode(): int { return 200; }
	public function setHeader(string $name, string $value): static { return $this; }
	public function addHeader(string $name, string $value): static { return $this; }
	public function setContentType(string $type, ?string $charset = null): static { return $this; }
	public function redirect(string $url, int $code = self::S302_Found): void {}
	public function setExpiration(?string $expire): static { return $this; }
	public function isSent(): bool { return false; }
	public function getHeader(string $header): ?string { return null; }
	public function getHeaders(): array { return []; }
}


/** Vrati hodnotu, kterou by si ReturnPath ulozil do cookie, nebo null, kdyz neuklada nic. */
function stored(string $url): ?string
{
	$response = new ResponseSpy();
	new ReturnPath(new Request(new UrlScript('https://admin.example/')), $response)->store(new UrlScript($url));

	return $response->cookie;
}


test('bezna cesta se pamatuje cela', function () {
	Assert::same('devices', stored('https://admin.example/devices'));
	Assert::same('devices?page=2&order=name', stored('https://admin.example/devices?page=2&order=name'));
});


test('signal se zahazuje, zbytek adresy zustava', function () {
	Assert::same('devices?page=2', stored('https://admin.example/devices?page=2&do=grid-export'));
	Assert::same('devices', stored('https://admin.example/devices?do=grid-export'));
});


test('kdyz po zahozeni signalu nic nezbyde, neuklada se nic', function () {
	Assert::null(stored('https://admin.example/?do=clearCache'));
});
