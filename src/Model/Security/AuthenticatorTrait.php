<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;
use ADT\FancyAdmin\Model\Queries\IdentityQuery;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;
use Nette\Security as NS;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;

/**
 * @method Identity authenticate(string $user, string $password, string $context, array $metadata = []))
 */
trait AuthenticatorTrait
{
	abstract protected function getEntityManager(): EntityManager;
	abstract protected function getOnetimeTokenFactory(): OnetimeTokenQueryFactory;
	abstract protected function getIdentityQueryFactory(): IdentityQueryFactory;

	const string USER_EMAIL = 'email';
	const string USER_PHONE_NUMBER = 'phone_number';

	public static function verifyPassword(string $password, string $hash): bool
	{
		return new NS\Passwords()->verify($password, $hash);
	}

	/**
	 * @throws AuthenticationException
	 */
	protected function verifyCredentials(string $user, string $password, ?string $context, array $metadata = []): Identity
	{
		$identityQuery = $this->getIdentityQueryFactory()->create();
		if ($this->validatePhoneNumber($user)) {
			$identityQuery->byPhoneNumber($user);
			$userType = static::USER_PHONE_NUMBER;
		} else {
			$identityQuery->byUsername($user);
			$userType = static::USER_EMAIL;
		}

		$this->initQueryObject($identityQuery);

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
				(!$this->getOnetimeTokenFactory()->create()->byIsValid()->byToken($password)->byType(OnetimeToken::TYPE_LOGIN)->fetchOneOrNull()) // TODO login
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

		return $identity;
	}

	protected function initQueryObject(IdentityQuery $query): void
	{
	}

	protected function getIdentity(string $id, string $token, array $metadata): ?IIdentity
	{
		$identity = $this->getEntityManager()->getRepository(Identity::class)->find($id);
		if (!$identity->getIsActive()) {
			return null;
		}
		$identity->setAuthToken($token);
		return $identity;
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
