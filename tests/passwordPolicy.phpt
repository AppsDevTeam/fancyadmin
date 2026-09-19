<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\PasswordPolicy;
use Tester\Assert;

/**
 * Politika hesel (nalez WEB-03, CWE-521).
 *
 * Politika drive visela na dvou pevnych radcich v ciselniku `configuration` a vybirala se
 * podle toho, jestli je identita administrator nebo ma backoffice ACL - zakaznicka identita
 * propadla bez kontroly. Ted ji nese role a identita jich muze mit vic naraz.
 */

require __DIR__ . '/bootstrap.php';


/**
 * Role jen s tim, na cem politice zalezi. strictestOf() bere iterable a saha jen na gettery
 * politiky, takze cely AclRole - entitu s Doctrine mapovanim a dvaadvaceti metodami -
 * sem tahat nemusime.
 */
function role(
	bool $enabled = true,
	?int $minLength = null,
	bool $upper = false,
	bool $lower = false,
	bool $digit = false,
	bool $special = false,
	?int $session = null,
): object {
	return new class ($enabled, $minLength, $upper, $lower, $digit, $special, $session) {
		public function __construct(
			private bool $enabled,
			private ?int $minLength,
			private bool $upper,
			private bool $lower,
			private bool $digit,
			private bool $special,
			private ?int $session,
		) {}

		public function getPasswordPolicyEnabled(): bool { return $this->enabled; }
		public function getPasswordMinLength(): ?int { return $this->minLength; }
		public function getPasswordRequireUppercase(): bool { return $this->upper; }
		public function getPasswordRequireLowercase(): bool { return $this->lower; }
		public function getPasswordRequireDigit(): bool { return $this->digit; }
		public function getPasswordRequireSpecialChar(): bool { return $this->special; }
		public function getSessionExpirationMinutes(): ?int { return $this->session; }
	};
}


test('zadna role s politikou znamena zadnou politiku', function () {
	Assert::null(PasswordPolicy::strictestOf([]));
	Assert::null(PasswordPolicy::strictestOf([role(enabled: false, minLength: 20)]));
});


test('vyhovujici heslo projde', function () {
	$policy = PasswordPolicy::strictestOf([role(minLength: 12, upper: true, lower: true, digit: true, special: true)]);

	Assert::same([], $policy->violations('Silne-Heslo1!'));
});


test('kazde pravidlo hlasi svou chybu', function () {
	$policy = PasswordPolicy::strictestOf([role(minLength: 12, upper: true, lower: true, digit: true, special: true)]);

	Assert::same(
		[
			'fcadmin.forms.newPassword.errors.minLength',
			'fcadmin.forms.newPassword.errors.requireUppercase',
			'fcadmin.forms.newPassword.errors.requireDigit',
			'fcadmin.forms.newPassword.errors.requireSpecialChar',
		],
		array_column($policy->violations('kratke'), 0)
	);
});


test('minLength nese svuj parametr do prekladu', function () {
	$policy = PasswordPolicy::strictestOf([role(minLength: 12)]);

	Assert::same(['fcadmin.forms.newPassword.errors.minLength', 12], $policy->violations('Ab1!')[0]);
});


test('role bez vyplnene delky ma vychozich 8 znaku', function () {
	$policy = PasswordPolicy::strictestOf([role()]);

	Assert::same([], $policy->violations('12345678'));
	Assert::same(['fcadmin.forms.newPassword.errors.minLength', 8], $policy->violations('1234567')[0]);
});


test('z vic roli plati nejprisnejsi cteni', function () {
	// Nejvyssi delka a logicke NEBO u pozadavku - jinak by jedna volna role zneplatnila
	// vsechny prisne.
	$policy = PasswordPolicy::strictestOf([
		role(minLength: 8, upper: true),
		role(minLength: 17, digit: true),
		role(minLength: 12, special: true),
	]);

	Assert::same(17, $policy->minLength);
	Assert::true($policy->requireUppercase);
	Assert::true($policy->requireDigit);
	Assert::true($policy->requireSpecialChar);
	Assert::false($policy->requireLowercase);
});


test('vypnuta role do slouceni nevstupuje', function () {
	$policy = PasswordPolicy::strictestOf([
		role(minLength: 12),
		role(enabled: false, minLength: 30, special: true),
	]);

	Assert::same(12, $policy->minLength);
	Assert::false($policy->requireSpecialChar);
});


test('expirace session je nejkratsi z nastavenych', function () {
	// Nenastavena hodnota a nula znamenaji "neomezovat", takze slouceni neovlivni.
	$policy = PasswordPolicy::strictestOf([
		role(session: 60),
		role(session: null),
		role(session: 0),
		role(session: 15),
	]);

	Assert::same(15, $policy->sessionExpirationMinutes);
	Assert::null(PasswordPolicy::strictestOf([role(), role()])->sessionExpirationMinutes);
});


test('heslo shodne s prihlasovacim udajem se odmita', function () {
	// Na politice role nezavisi, plati vzdy.
	Assert::true(PasswordPolicy::matchesIdentifier('jan@example.com', 'jan@example.com', null));
	Assert::true(PasswordPolicy::matchesIdentifier('JAN@EXAMPLE.COM', 'jan@example.com', null));
	Assert::true(PasswordPolicy::matchesIdentifier('jan', 'jan@example.com', null));
	Assert::true(PasswordPolicy::matchesIdentifier('pokladni1', null, 'Pokladni1'));
});


test('heslo, ktere prihlasovaci udaj jen obsahuje, projde', function () {
	Assert::false(PasswordPolicy::matchesIdentifier('jan@example.com-A1!', 'jan@example.com', null));
	Assert::false(PasswordPolicy::matchesIdentifier('Silne-Heslo1!', 'jan@example.com', 'jan'));
});


test('prazdny udaj nic nezakazuje', function () {
	Assert::false(PasswordPolicy::matchesIdentifier('', null, null));
	Assert::false(PasswordPolicy::matchesIdentifier('', '', ''));
});
