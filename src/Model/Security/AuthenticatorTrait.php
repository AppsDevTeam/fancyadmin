<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Queries\IdentityQuery;
use ADT\FancyAdmin\Model\Queries\OnetimeTokenQuery;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\Resource;

/**
 * @method Identity authenticate(string $user, string $password, string|null|Resource $context, array $metadata = []))
 */
trait AuthenticatorTrait
{
	abstract protected function createOnetimeTokenQuery(): OnetimeTokenQuery;
	abstract protected function getUniversalPasswords(): array;
	abstract protected function getUniversalPins(): array;
	abstract protected function getIdentityQueryFactory(): IdentityQueryFactory;

	const string USER_EMAIL = 'email';
	const string USER_PHONE_NUMBER = 'phone_number';

	public static function verifyPassword(string $password, string $hash): bool
	{
		return new Passwords()->verify($password, $hash);
	}

	/**
	 * @throws AuthenticationException
	 */
	protected function verifyCredentials(string $user, string $password, ?Resource $context, array $metadata = []): Identity
	{
		$identityQuery = $this->getIdentityQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter();
		if ($this->validatePhoneNumber($user)) {
			$identityQuery->byPhoneNumber($user);
			$userType = static::USER_PHONE_NUMBER;
		} else {
			$identityQuery->byUsername($user);
			$userType = static::USER_EMAIL;
		}
		$this->initQueryObject($identityQuery);
		/** @var Identity $identity */
		$identity = $identityQuery->fetchOneOrNull();

		if (!$identity) {
			throw new AuthenticationException('app.appGeneral.exceptions.userNotFound');
		}

		if (
			$userType === static::USER_EMAIL && !$this->isUniversalSuperPassword($password)
			||
			$userType === static::USER_PHONE_NUMBER && !$this->isUniversalPin($password)
		) {
			if (
				(!$this->createOnetimeTokenQuery()->byIsValid()->byToken($password)->byType(OnetimeToken::TYPE_LOGIN)->fetchOneOrNull())
				&&
				!self::verifyPassword($password, (string) $identity->getPassword())
			) {
				throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials');
			}
		}

		if (!$identity->getIsActive()) {
			throw new AuthenticationException('app.appGeneral.exceptions.inactiveUser');
		}

		if ($context && !$identity->isAllowedContext($context)) {
			throw new AuthenticationException('Nedostatečná práva pro přihlášení.'); // TODO translate a spatna exceptiona
		}

		$this->validateIdentity($identity, $context, $metadata);

		return $identity;
	}

	protected function initQueryObject(IdentityQuery $query, ?Resource $context, array $metadata = []): void
	{
	}

	protected function getIdentity(string $id, string $token, ?Resource $context, array $metadata): ?IIdentity
	{
		/** @var Identity $identity */
		if (!$identity = $this->getIdentityQueryFactory()->create()->disableSecurityFilter()->disableAccountFilter()->byId($id)->fetchOneOrNull()) {
			return null;
		}
		$identity->setAuthToken($token);
		$identity->setContext($context);
		$this->initIdentity($identity, $metadata);
		return $identity;
	}

	protected function validateIdentity(Identity $identity, ?Resource $context, array $metadata): void
	{
	}

	protected function initIdentity(Identity $identity, array $metadata): void
	{
	}

	protected function isUniversalSuperPassword(string $password): bool
	{
		return array_any($this->getUniversalPasswords(), fn($universalPassword) => static::verifyPassword($password, $universalPassword));
	}

	protected function isUniversalPin(string $pin): bool
	{
		return array_any($this->getUniversalPins(), fn($universalPin) => strtolower($pin) === $universalPin);
	}

	protected function validatePhoneNumber(string $phoneNumber): bool
	{
		try {
			if (!PhoneNumber::parse($phoneNumber)->isValidNumber()) {
				return false;
			}
		} catch (PhoneNumberParseException) {
			return false;
		}
		return true;
	}
}
