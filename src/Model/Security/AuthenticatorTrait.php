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
use Nette\Utils\Validators;

/**
 * @method Identity authenticate(string $user, string $password, ?string $context = null, array $metadata = []))
 */
trait AuthenticatorTrait
{
	abstract protected function createOnetimeTokenQuery(): OnetimeTokenQuery;
	abstract protected function getUniversalPasswords(): array;
	abstract protected function getUniversalPins(): array;
	abstract protected function getIdentityQueryFactory(): IdentityQueryFactory;
	
	public static function verifyPassword(string $password, string $hash): bool
	{
		return new Passwords()->verify($password, $hash);
	}

	/**
	 * @throws AuthenticationException
	 */
	protected function verifyCredentials(string $user, string $password, ?string $context = null, array $metadata = []): Identity
	{
		$identityQuery = $this->getIdentityQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter();
		if ($this->validatePhoneNumber($user)) {
			$identityQuery->byPhoneNumber($user);
			$userType = UserTypeEnum::PHONE_NUMBER;
		} elseif (Validators::isEmail($user)) {
			$identityQuery->byEmail($user);
			$userType = UserTypeEnum::EMAIL;
		} else {
			$identityQuery->byUsername($user);
			$userType = UserTypeEnum::USERNAME;
		}
		$identityQuery->byContext($context);
		
		$this->initQueryObject($identityQuery, $userType, $context, $metadata);
		/** @var Identity $identity */
		$identity = $identityQuery->fetchOneOrNull();

		if (!$identity) {
			throw new AuthenticationException('app.appGeneral.exceptions.userNotFound');
		}

		if (
			in_array($userType, [UserTypeEnum::EMAIL, UserTypeEnum::USERNAME]) && !$this->isUniversalSuperPassword($password)
			||
			$userType === UserTypeEnum::PHONE_NUMBER && !$this->isUniversalPin($password)
		) {
			if (
				(!$this->createOnetimeTokenQuery()->byIsValid()->byToken($password)->byType(OnetimeToken::TYPE_LOGIN)->fetchOneOrNull())
				&&
				!self::verifyPassword($password, (string) $identity->getPassword())
			) {
				throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials'); // TODO translate
			}
		}

		if (!$identity->getIsActive()) {
			throw new AuthenticationException('app.appGeneral.exceptions.inactiveUser'); // TODO translate
		}

		$this->validateIdentity($identity, $context, $metadata);

		return $identity;
	}

	protected function initQueryObject(IdentityQuery $query, UserTypeEnum $userType, ?string $context = null, array $metadata = []): void
	{
	}

	protected function getIdentity(string $id, ?string $context): ?IIdentity
	{
		return $this->getIdentityQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byId($id)
			->byContext($context)
			->fetchOneOrNull();
	}

	protected function validateIdentity(Identity $identity, ?string $context = null, array $metadata = []): void
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
