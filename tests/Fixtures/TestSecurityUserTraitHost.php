<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Security\AuthenticatorTrait;
use ADT\FancyAdmin\Model\Security\SecurityUserTrait;
use ADT\DoctrineAuthenticator\OTP\Identity as OtpIdentity;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use SensitiveParameter;

/**
 * Nette\Security\User v roli predka - trait vola parent::login(), takze potrebuje nekoho
 * nad sebou. Skutecny User by si sahal na uloziste session, tady staci zaznam volani.
 */
abstract class LoginRecorder
{
	/** @var list<array{string|IIdentity, ?string, ?string, array}> */
	public array $loginCalls = [];

	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = [],
	): void
	{
		$this->loginCalls[] = [$username, $password, $context, $metadata];
	}
}

final class TestSecurityUserTraitHost extends LoginRecorder
{
	use SecurityUserTrait;

	public function __construct(
		private readonly ?IIdentity $identity = null,
		private readonly ?Authorizator $authorizator = null,
	) {
	}

	public function getIdentity(): ?IIdentity
	{
		return $this->identity;
	}

	protected function getAuthorizator(): Authorizator
	{
		return $this->authorizator;
	}
}

/** Autentizator projektu - balicek dodava jen kontrolu opravneni k prihlaseni. */
final class TestAuthenticator
{
	use AuthenticatorTrait;

	public function callValidateIdentity(OtpIdentity $identity): void
	{
		$this->validateIdentity($identity);
	}
}
