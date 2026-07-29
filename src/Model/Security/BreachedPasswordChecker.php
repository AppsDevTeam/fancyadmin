<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

class BreachedPasswordChecker
{
	private ?array $commonPasswords = null;

	private string $commonPasswordsFile;

	public function __construct()
	{
		$this->commonPasswordsFile = __DIR__ . '/assets/common-passwords.txt';
	}

	public function isBreached(string $password): bool
	{
		return $this->isInCommonList($password) || $this->isInHaveIBeenPwned($password);
	}

	private function isInCommonList(string $password): bool
	{
		if ($this->commonPasswords === null) {
			$content = @file_get_contents($this->commonPasswordsFile);
			if ($content === false) {
				$this->commonPasswords = [];
				return false;
			}
			$this->commonPasswords = array_flip(
				array_map('trim', explode("\n", $content))
			);
		}

		return isset($this->commonPasswords[strtolower($password)]);
	}

	private function isInHaveIBeenPwned(string $password): bool
	{
		$sha1 = strtoupper(sha1($password));
		$prefix = substr($sha1, 0, 5);
		$suffix = substr($sha1, 5);

		$ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT_MS => 500,
			CURLOPT_CONNECTTIMEOUT_MS => 500,
			CURLOPT_HTTPHEADER => ['User-Agent: FancyAdmin-ASVS-Checker'],
		]);
		$response = curl_exec($ch);

		if ($response === false) {
			return false;
		}

		foreach (explode("\n", $response) as $line) {
			[$hashSuffix] = explode(':', trim($line), 2);
			if (strtoupper($hashSuffix) === $suffix) {
				return true;
			}
		}

		return false;
	}
}
