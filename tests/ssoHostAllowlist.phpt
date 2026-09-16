<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\SsoHostAllowlist;
use Tester\Assert;

/**
 * Allowlist verejne URL SSO (nalez WEB-09, CWE-601).
 *
 * Verejna URL je adresa, na kterou odchazi prohlizec neprihlaseneho navstevnika v ramci
 * tiche kontroly OAuth. Do teto zmeny do ni sel libovolny host, takze z duveryhodne
 * domeny vedl odkaz na stranku utocnika.
 */

require __DIR__ . '/bootstrap.php';


$allowlist = new SsoHostAllowlist(['auth.example.com']);


test('povoleny host projde', function () use ($allowlist) {
	Assert::true($allowlist->allows('https://auth.example.com'));
	Assert::true($allowlist->allows('https://auth.example.com/realms/test'));
	Assert::true($allowlist->allows('https://auth.example.com:8443'));
});


test('velikost pismen nerozhoduje', function () {
	Assert::true(new SsoHostAllowlist(['AUTH.example.com'])->allows('https://auth.EXAMPLE.com'));
});


test('cizi host se odmita', function () use ($allowlist) {
	Assert::false($allowlist->allows('https://collaborator.example.net'));
});


test('porovnava se cely host, ne prefix ani suffix', function () use ($allowlist) {
	Assert::false($allowlist->allows('https://auth.example.com.evil.com'));
	Assert::false($allowlist->allows('https://evil.auth.example.com'));
	Assert::false($allowlist->allows('https://auth.example.com./'));
});


test('trik s userinfo a zpetnym lomitkem neprojde', function () use ($allowlist) {
	// Skutecny host je az za zavinacem, resp. za lomitkem - prefix jen mate oko.
	Assert::false($allowlist->allows('https://auth.example.com@evil.com/'));
	Assert::false($allowlist->allows('https://auth.example.com\\@evil.com'));
	Assert::false($allowlist->allows('https://evil.com#@auth.example.com'));
	Assert::false($allowlist->allows('//evil.com'));
	Assert::false($allowlist->allows('https:/\\evil.com'));
});


test('homograf s cyrilici je jiny host', function () use ($allowlist) {
	Assert::false($allowlist->allows('https://аuth.example.com'));
	Assert::false($allowlist->allows('https://xn--uth-8cd.example.com'));
});


test('hodnota bez hostu se odmita', function () use ($allowlist) {
	Assert::false($allowlist->allows('javascript:alert(1)//auth.example.com'));
	Assert::false($allowlist->allows('auth.example.com'));
	Assert::false($allowlist->allows(''));
	Assert::false($allowlist->allows(null));
});


test('prazdny allowlist nepousti nic', function () {
	// Fail-closed: projekt, ktery SSO pouziva, si hosty vypsat musi.
	$empty = new SsoHostAllowlist([]);

	Assert::false($empty->allows('https://auth.example.com'));
	Assert::same([], $empty->getHosts());
});


test('seznam hostu se vraci male', function () {
	Assert::same(['auth.example.com'], new SsoHostAllowlist(['AUTH.Example.COM'])->getHosts());
});
