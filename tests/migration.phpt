<?php

declare(strict_types=1);

use Nette\Utils\Json;
use Tester\Assert;

/**
 * Migrace, ktere balicek dodava projektum.
 *
 * Starsi z nich zaklada politiku hesel jako polozky ciselniku configuration, novejsi ji
 * presouva na roli (acl_role). Obe se testuji proti tomu, co z nich aplikace cte - kdyz
 * se klice nebo sloupce rozejdou, politika se tise neprojevi.
 *
 * Tridy se nedaji instanciovat: dedi z Doctrine\Migrations\AbstractMigration, a doctrine/migrations
 * neni zavislost balicku (dodava ji projekt). Testuje se proto obsah souboru.
 */

require __DIR__ . '/bootstrap.php';


/** Puvodni politika v ciselniku configuration - projekty, ktere ji uz maji, ji nesmi ztratit. */
const MIGRATION_FILE = __DIR__ . '/../src/Migrations/Version20260329120000.php';

/** Presun politiky na roli. */
const POLICY_MIGRATION_FILE = __DIR__ . '/../src/Migrations/Version20260916130000.php';

/** @return list<string> SQL prikazy z dane metody */
function migrationSql(string $method): array
{
	$source = (string) file_get_contents(MIGRATION_FILE);

	preg_match('~function ' . $method . '\(Schema \$schema\): void\s*\{(.*?)\n    }~s', $source, $body);
	Assert::true(isset($body[1]), 'Metoda ' . $method . '() se v migraci nenasla.');

	preg_match_all('~\$this->addSql\("(.*?)"\);~s', $body[1], $matches);

	return array_map(fn(string $sql) => str_replace('\\"', '"', $sql), $matches[1]);
}

function policyFromSql(string $sql): array
{
	preg_match("~'(\{.*\})'~", $sql, $match);
	Assert::true(isset($match[1]), 'V prikazu neni JSON: ' . $sql);

	return Json::decode($match[1], forceArrays: true);
}


test('migrace zaklada obe politiky hesel', function () {
	$statements = migrationSql('up');

	Assert::count(2, $statements);
	Assert::contains("'password.policy.backoffice'", $statements[0]);
	Assert::contains("'password.policy.admin'", $statements[1]);
});


test('politiky se zakladaji jako JSON konfigurace', function () {
	foreach (migrationSql('up') as $sql) {
		Assert::contains('INSERT INTO configuration', $sql);
		Assert::contains("'json'", $sql);
		// updated_at je NOT NULL, bez nej by insert spadl.
		Assert::contains('updated_at', $sql);
	}
});


test('obe politiky jsou po instalaci vypnute', function () {
	// Zapnuti je vedome rozhodnuti projektu - migrace nikomu nezmeni chovani prihlaseni.
	foreach (migrationSql('up') as $sql) {
		Assert::false(policyFromSql($sql)['enabled']);
	}
});


test('tvar politiky odpovida tomu, co cte aplikace', function () {
	foreach (migrationSql('up') as $sql) {
		$policy = policyFromSql($sql);

		Assert::same([
			'enabled',
			'minLength',
			'requireUppercase',
			'requireLowercase',
			'requireDigit',
			'requireSpecialChar',
			'sessionExpirationMinutes',
		], array_keys($policy));

		Assert::type('int', $policy['minLength']);
		Assert::true($policy['requireUppercase']);
		Assert::true($policy['requireLowercase']);
		Assert::true($policy['requireDigit']);
		Assert::true($policy['requireSpecialChar']);
		// Bez vyplnene hodnoty se expirace session neupravuje - viz SessionExpirationCallback.
		Assert::null($policy['sessionExpirationMinutes']);
	}
});


test('admin ma prisnejsi minimalni delku hesla nez backoffice', function () {
	$statements = migrationSql('up');

	$backoffice = policyFromSql($statements[0]);
	$admin = policyFromSql($statements[1]);

	Assert::same(12, $backoffice['minLength']);
	Assert::same(17, $admin['minLength']);
	Assert::true($admin['minLength'] > $backoffice['minLength']);
});


test('migrace jde vzit zpet', function () {
	$statements = migrationSql('down');

	Assert::count(1, $statements);
	Assert::contains('DELETE FROM configuration', $statements[0]);
	Assert::contains("'password.policy.backoffice'", $statements[0]);
	Assert::contains("'password.policy.admin'", $statements[0]);
});


test('sloupce politiky na roli sedi s tim, co mapuje AclRoleTrait', function () {
	// Politika uz nesedi v ciselniku configuration, ale na roli, takze klice nehlida
	// SessionExpirationCallback - hlida je mapovani entity, ze ktere se politika cte.
	$migration = (string) file_get_contents(POLICY_MIGRATION_FILE);
	$trait = (string) file_get_contents(__DIR__ . '/../src/Model/Entities/AclRoleTrait.php');

	$sloupce = [
		'password_policy_enabled' => 'passwordPolicyEnabled',
		'password_min_length' => 'passwordMinLength',
		'password_require_uppercase' => 'passwordRequireUppercase',
		'password_require_lowercase' => 'passwordRequireLowercase',
		'password_require_digit' => 'passwordRequireDigit',
		'password_require_special_char' => 'passwordRequireSpecialChar',
		'session_expiration_minutes' => 'sessionExpirationMinutes',
	];

	foreach ($sloupce as $sloupec => $vlastnost) {
		Assert::contains('ADD ' . $sloupec, $migration, $sloupec);
		Assert::contains('DROP ' . $sloupec, $migration, $sloupec);
		Assert::contains('$' . $vlastnost, $trait, $vlastnost);
	}
});


test('expirace session se cte z politiky role, ne z ciselniku', function () {
	$source = (string) file_get_contents(__DIR__ . '/../src/Model/Security/SessionExpirationCallback.php');

	Assert::contains('PasswordPolicy::strictestOf', $source);
	Assert::contains('sessionExpirationMinutes', $source);
	// Zbytek po ciselniku by znamenal, ze se politika cte ze dvou mist naraz.
	Assert::notContains('password.policy.', $source);
});
