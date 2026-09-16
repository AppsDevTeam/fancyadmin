<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\AclRole;

/**
 * Politika hesel, kterou nese role.
 *
 * Trida je zamerne bez zavislosti na frameworku, aby sla testovat: zapojeni do formularu
 * resi UI\Components\Forms\PasswordPolicyValidationTrait, tady je jen rozhodovani.
 */
final readonly class PasswordPolicy
{
	private const int DEFAULT_MIN_LENGTH = 8;

	private function __construct(
		public int $minLength,
		public bool $requireUppercase,
		public bool $requireLowercase,
		public bool $requireDigit,
		public bool $requireSpecialChar,
		public ?int $sessionExpirationMinutes,
	) {
	}

	/**
	 * Nejprisnejsi politika z rolí identity, nebo null kdyz ji zadna nema zapnutou.
	 *
	 * Identita muze mit vic roli naraz a kazda smi mit vlastni politiku. Slucuje se proto
	 * na nejprisnejsi cteni: nejvyssi minimalni delka, logicke NEBO u pozadavku na znaky
	 * a nejkratsi z nastavenych expiraci session. Opacne poradi by znamenalo, ze jedna
	 * volna role zneplatni vsechny prisne.
	 *
	 * @param iterable<AclRole> $roles
	 */
	public static function strictestOf(iterable $roles): ?self
	{
		$policy = null;

		foreach ($roles as $_role) {
			if (!$_role->getPasswordPolicyEnabled()) {
				continue;
			}

			$minutes = $_role->getSessionExpirationMinutes();

			$policy = new self(
				max($policy?->minLength ?? 0, $_role->getPasswordMinLength() ?? self::DEFAULT_MIN_LENGTH),
				$policy?->requireUppercase || $_role->getPasswordRequireUppercase(),
				$policy?->requireLowercase || $_role->getPasswordRequireLowercase(),
				$policy?->requireDigit || $_role->getPasswordRequireDigit(),
				$policy?->requireSpecialChar || $_role->getPasswordRequireSpecialChar(),
				match (true) {
					$minutes === null || $minutes <= 0 => $policy?->sessionExpirationMinutes,
					$policy?->sessionExpirationMinutes === null => $minutes,
					default => min($policy->sessionExpirationMinutes, $minutes),
				},
			);
		}

		return $policy;
	}

	/**
	 * Porusena pravidla. Prvni prvek kazde polozky je prekladovy klic, druhy jeho
	 * parametr - ten ma jen minLength.
	 *
	 * @return array<int, array{0: string, 1?: int}>
	 */
	public function violations(string $password): array
	{
		$violations = [];

		if (mb_strlen($password) < $this->minLength) {
			$violations[] = ['fcadmin.forms.newPassword.errors.minLength', $this->minLength];
		}
		if ($this->requireUppercase && !preg_match('/[A-Z]/', $password)) {
			$violations[] = ['fcadmin.forms.newPassword.errors.requireUppercase'];
		}
		if ($this->requireLowercase && !preg_match('/[a-z]/', $password)) {
			$violations[] = ['fcadmin.forms.newPassword.errors.requireLowercase'];
		}
		if ($this->requireDigit && !preg_match('/\d/', $password)) {
			$violations[] = ['fcadmin.forms.newPassword.errors.requireDigit'];
		}
		if ($this->requireSpecialChar && !preg_match('/[^a-zA-Z0-9]/', $password)) {
			$violations[] = ['fcadmin.forms.newPassword.errors.requireSpecialChar'];
		}

		return $violations;
	}

	/**
	 * Heslo shodne s prihlasovacim udajem: s e-mailem, s jeho casti pred zavinacem nebo
	 * s uzivatelskym jmenem. Porovnava se cela hodnota bez ohledu na velikost pismen -
	 * heslo, ktere e-mail jen obsahuje, uhodnutelne neni a prochazi.
	 *
	 * Na politice role nezavisi, plati vzdy.
	 */
	public static function matchesIdentifier(string $password, ?string $email, ?string $username): bool
	{
		$email = (string) $email;

		$forbidden = array_filter([
			$email,
			$email !== '' ? explode('@', $email)[0] : '',
			(string) $username,
		]);

		foreach ($forbidden as $_value) {
			if (mb_strtolower($password) === mb_strtolower($_value)) {
				return true;
			}
		}

		return false;
	}
}
