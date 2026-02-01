<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Queries\IdentityQuery;
use ADT\FancyAdmin\Model\Queries\OnetimeTokenQuery;
use ADT\FancyAdmin\Model\Services\OnetimeTokenService;
use ADT\FancyAdmin\Model\Services\OnetimeTokenTypeEnum;
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
	abstract protected function getOnetimeTokenService(): OnetimeTokenService;
	abstract protected function getUniversalPasswords(): array;
	abstract protected function getIdentityQueryFactory(): IdentityQueryFactory;
	abstract protected function getEntityManager(): EntityManager;
	
	protected function verifyPassword(string $password, string $hash): bool
	{
		return new Passwords()->verify($password, $hash);
	}

	/**
	 * @throws AuthenticationException
	 */
	protected function verifyCredentials(string $user, ?string $password = null, ?string $context = null, array $metadata = []): DoctrineAuthenticatorIdentity
	{
		if (!$password) {
			/** @var OnetimeToken $onetimeToken */
			if (!$onetimeToken = $this->getOnetimeTokenService()->findToken(OnetimeTokenTypeEnum::LOGIN, $user)) {
				throw new AuthenticationException('fcadmin.appGeneral.exceptions.wrongCredentials');
			}

			if (!$identity = $this->getEntityManager()->getRepository($onetimeToken->getObjectClass())->find($onetimeToken->getObjectId())) {
				throw new AuthenticationException('fcadmin.appGeneral.exceptions.wrongCredentials');
			}
			
			$identity->setOnetimeToken($onetimeToken);
		} else {
			/** @var Identity $identity */
			if (!$identity = $this->findIdentity($user, $context, $metadata)) {
				throw new AuthenticationException('fcadmin.appGeneral.exceptions.wrongCredentials');
			}

			if (!array_any($this->getUniversalPasswords(), fn($universalPassword) => $this->verifyPassword($password, $universalPassword))) {
				if (
					!$this->verifyPassword($password, (string) $identity->getPassword())
					&&
					!$this->getOnetimeTokenService()->findToken(OnetimeToken::TYPE_LOGIN, $password)->fetchOneOrNull()
				) {
					throw new AuthenticationException('fcadmin.appGeneral.exceptions.wrongCredentials');
				}
			}
		}

		if (!$identity->getIsActive()) {
			throw new AuthenticationException('fcadmin.appGeneral.exceptions.inactiveUser'); // TODO translate
		}

		$this->validateIdentity($identity, $context, $metadata);

		return $identity;
	}

	protected function initQueryObject(IdentityQuery $query, UserTypeEnum $userType, ?string $context = null, array $metadata = []): void
	{
	}

	public function findIdentity(string $identifier, ?string $context = null, array $metadata = []): ?IIdentity
	{
		$identityQuery = $this->getIdentityQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byContext($context);

		if ($this->validatePhoneNumber($identifier)) {
			$identityQuery->byPhoneNumber($identifier);
			$userType = UserTypeEnum::PHONE;
		} elseif (Validators::isEmail($identifier)) {
			$identityQuery->byEmail($identifier);
			$userType = UserTypeEnum::EMAIL;
		} else {
			$identityQuery->byUsername($identifier);
			$userType = UserTypeEnum::USERNAME;
		}

		$this->initQueryObject($identityQuery, $userType, $context, $metadata);
		
		return $identityQuery->fetchOneOrNull();
	}

	protected function validateIdentity(Identity $identity, ?string $context = null, array $metadata = []): void
	{
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
