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
	abstract protected function getIdentityQueryFactory(): IdentityQueryFactory;
	
	protected function verifyPassword(string $password, string $hash): bool
	{
		return new Passwords()->verify($password, $hash);
	}

	/**
	 * @throws AuthenticationException
	 */
	protected function verifyCredentials(string $user, ?string $password = null, ?string $context = null, array $metadata = []): Identity
	{
		if (!$password) {
			/** @var OnetimeToken $onetimeToken */
			if (!$onetimeToken = $this->createOnetimeTokenQuery()->byIsValid()->byToken($user)->byType(OnetimeToken::TYPE_LOGIN)->fetchOneOrNull()) {
				throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials'); // TODO translate
			}

			$onetimeToken->setUsedAt(new \DateTimeImmutable());
			$this->em->flush();

			if (!$identity = $this->em->getRepository($onetimeToken->getObjectClass())->find($onetimeToken->getObjectId())) {
				throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials'); // TODO translate
			}
		} else {
			/** @var Identity $identity */
			if (!$identity = $this->findIdentityByCredentials($user, $context, $metadata)) {
				throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials'); // TODO translate
			}

			if (!array_any($this->getUniversalPasswords(), fn($universalPassword) => $this->verifyPassword($password, $universalPassword))) {
				if (!$this->verifyPassword($password, (string) $identity->getPassword())) {
					throw new AuthenticationException('app.appGeneral.exceptions.wrongCredentials'); // TODO translate
				}
			}
		}

		if (!$identity->getIsActive()) {
			throw new AuthenticationException('app.appGeneral.exceptions.inactiveUser'); // TODO translate
		}

		$this->validateIdentity($identity, $context, $metadata);

		return $identity;
	}

	protected function initQueryObject(IdentityQuery $query, ?string $context = null, array $metadata = []): void
	{
	}

	protected function findIdentityByCredentials(string $user, ?string $context = null, array $metadata = []): ?IIdentity
	{
		$identityQuery = $this->getIdentityQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byContext($context);

		if ($this->validatePhoneNumber($user)) {
			$identityQuery->byPhoneNumber($user);
		} elseif (Validators::isEmail($user)) {
			$identityQuery->byEmail($user);
		} elseif (is_numeric($user)) {
			$identityQuery->byId($user);
		} else {
			$identityQuery->byUsername($user);
		}

		$this->initQueryObject($identityQuery, $context, $metadata);
		
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
