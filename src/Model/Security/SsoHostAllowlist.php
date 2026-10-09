<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use Nette\Http\Url;
use Throwable;

/**
 * Allowlist hostu, na ktere smi mirit verejna URL SSO instance (nalez WEB-09, CWE-601).
 *
 * Verejna URL je adresa, na kterou prohlizec neprihlaseneho navstevnika odchazi v ramci
 * tiche kontroly OAuth. Kdo ji smi nastavit, umel z duveryhodne domeny udelat odrazovy
 * mustek na cizi stranku. Balicek proto dovoli jen hosty vypsane v konfiguraci projektu.
 */
final readonly class SsoHostAllowlist
{
	/** @var list<string> */
	private array $hosts;

	/** @param list<string> $hosts */
	public function __construct(array $hosts)
	{
		$this->hosts = array_values(array_map(mb_strtolower(...), $hosts));
	}

	/**
	 * Porovnava se cely host, ne prefix ani suffix: `auth.example.com.evil.com`
	 * ani `evil.auth.example.com` shodu nedavaji. Prazdny allowlist nepousti nic.
	 */
	public function allows(?string $url): bool
	{
		if ($url === null || $url === '') {
			return false;
		}

		try {
			$host = new Url($url)->getHost();
		} catch (Throwable) {
			return false;
		}

		return $host !== '' && in_array(mb_strtolower($host), $this->hosts, true);
	}

	/** @return list<string> */
	public function getHosts(): array
	{
		return $this->hosts;
	}
}
