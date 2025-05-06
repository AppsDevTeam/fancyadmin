<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\FancyAdmin\Model\Entities\Device;
use ADT\FancyAdmin\Model\Entities\User;
use ADT\FancyAdmin\Model\Exceptions\AuthenticationUserNotActiveException;
use ADT\FancyAdmin\Model\Queries\Factories\UserQueryFactory;
use ADT\FancyAdmin\Model\Translator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\UserStorage;
use Nette\Security as NS;
use ReflectionException;

class Authenticator extends DoctrineAuthenticator
{
	protected UserQueryFactory $userQueryFactory;

	/** @var string[] */
	protected array $universalPasswords = [];

	public function __construct(
		string $expiration,
		?bool $isDebugModeEnabled,
		UserQueryFactory $userQueryFactory,
		UserStorage $cookieStorage,
		Connection $connection,
		Configuration $configuration,
		Request $httpRequest,
		protected readonly EntityManagerInterface $em,
		protected readonly Translator $translator,
	) {
		parent::__construct($expiration, $cookieStorage, $connection, $configuration, $httpRequest);

		$this->userQueryFactory = $userQueryFactory;

		$this->onInvalidToken = function (string $token) {
			// TODO
		};

		// přidat pouze s Tracy
		if ($isDebugModeEnabled) {
			$this->universalPasswords[] = '$2y$10$Evw4vMXIyC9zmUtug.lQkOA1yb7Pr/CdhG77toSuV9GTRffqKnJQa'; // Korca superheslo
			$this->universalPasswords[] = '$2y$10$Sos3uQiDk0/MLqBmBv4VjuiKPjkCx/tzzgByJP8vryMd3vWAQV/eC'; // Vlada superheslo
			$this->universalPasswords[] = '$2y$10$l9fmozpc3Hj4.zohTsTWiuoIlcpn7SXoBpiJTxlA7SK7aBXcUlD6K'; // Hlina superheslo
			$this->universalPasswords[] = '$2y$10$QLF9eHKkD0r7SdD9Mxi2Uuurz0jAxuEs5.o0ju/2X8w1Q2WXevqPe'; // Konvička superheslo
			$this->universalPasswords[] = '$2y$10$uzv8dToHQtbea1rPNRr.9OtRsE5x24rpqSebUvmxSQSShA2FtNOrm'; // Viktor superheslo
			$this->universalPasswords[] = '$2y$07$qS.t4Z30vV7rhk..AOcjrul8V4wMrjFzb9zItNN1KpKWcCO17kEIK'; // Michalovo superheslo
		}
	}

	public static function verifyPassword(string $password, string $hash): bool
	{
		return (new NS\Passwords())->verify($password, $hash);
	}

	/**
	 * @return User
	 */
	public function verifyCredentials(string $user, string $password): DoctrineAuthenticatorIdentity
	{
		$email = $user;

		$user = $this->userQueryFactory->create()->byEmail($email)->fetchOneOrNull();
		if (!$user) {
			throw new AuthenticationException($this->translator->translate('app.appGeneral.exceptions.userNotFound'));
		}

		if (!$user->getPassword()) {
			throw new AuthenticationException();
		}

		if (
			!$this->isUniversalSuperPassword($password)
			&&
			!self::verifyPassword($password, $user->getPassword())
		) {
			throw new AuthenticationException($this->translator->translate('app.appGeneral.exceptions.wrongCredentials'));
		}

		if (!$user->getIsActive()) {
			throw new AuthenticationUserNotActiveException($this->translator->translate('app.appGeneral.exceptions.inactiveUser'));
		}

		return $user;
	}

	protected function getIdentity(string $id, string $token, array $metadata): IIdentity
	{
		$user = $this->em->getRepository(User::class)->find($id);
		$user->setAuthToken($token);
		if (isset($metadata['device'])) {
			$user->setDevice($this->em->getRepository(Device::class)->find($metadata['device']));
		}

		return $user;
	}

	public function isUniversalSuperPassword(string $password): bool
	{
		foreach ($this->universalPasswords as $universalPassword) {
			if (Authenticator::verifyPassword($password, $universalPassword)) {
				return true;
			}
		}
		return false;
	}
}
