<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Mailer\Mailer;
use ADT\FancyAdmin\Model\Mailer\MailerTrait;
use Tester\Assert;

/**
 * Interface Mailer vs. volání ve fancyadminu.
 *
 * ResetPassword a IdentityProfileFormTrait volají mailer přes interface s pojmenovaným
 * checkLimit. Když ho interface nemá, projekt s vlastní implementací (bez MailerTrait)
 * padá na „Unknown named parameter $checkLimit".
 */

require __DIR__ . '/bootstrap.php';


/** @return array<string, ReflectionParameter> */
function parametersOf(string $class, string $method): array
{
	$parameters = [];
	foreach (new ReflectionMethod($class, $method)->getParameters() as $parameter) {
		$parameters[$parameter->getName()] = $parameter;
	}

	return $parameters;
}


foreach (['sendAccountCreationEmail', 'sendPasswordRecoveryMail', 'sendTwoFactorCodeMail'] as $method) {
	test("interface $method má volitelný checkLimit s výchozí hodnotou true", function () use ($method) {
		$parameters = parametersOf(Mailer::class, $method);

		Assert::true(isset($parameters['checkLimit']));
		Assert::same('bool', (string) $parameters['checkLimit']->getType());
		Assert::true($parameters['checkLimit']->isOptional());
		Assert::true($parameters['checkLimit']->getDefaultValue());
	});

	test("MailerTrait::$method má stejnou signaturu jako interface", function () use ($method) {
		$toSignature = fn(array $parameters) => array_map(
			fn(ReflectionParameter $p) => [(string) $p->getType(), $p->isOptional() ? $p->getDefaultValue() : null],
			$parameters,
		);

		Assert::same(
			$toSignature(parametersOf(Mailer::class, $method)),
			$toSignature(parametersOf(MailerTrait::class, $method)),
		);
	});
}


test('fancyadmin nepředává mailerům pojmenovaný parametr, který interface nemá', function () {
	$interfaceParameters = [];
	foreach (new ReflectionClass(Mailer::class)->getMethods() as $method) {
		$interfaceParameters[$method->getName()] = array_keys(parametersOf(Mailer::class, $method->getName()));
	}

	$violations = [];
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src'));
	foreach ($files as $file) {
		if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Model/Mailer/')) {
			continue;
		}
		$code = (string) file_get_contents($file->getPathname());
		preg_match_all('~->(send\w+)\(([^;]*)\);~', $code, $calls, PREG_SET_ORDER);
		foreach ($calls as [, $method, $arguments]) {
			if (!isset($interfaceParameters[$method])) {
				continue;
			}
			preg_match_all('~\b(\w+):\s~', $arguments, $named);
			foreach (array_diff($named[1], $interfaceParameters[$method]) as $unknown) {
				$violations[] = basename($file->getPathname()) . ": $method($unknown:)";
			}
		}
	}

	Assert::same([], $violations);
});
