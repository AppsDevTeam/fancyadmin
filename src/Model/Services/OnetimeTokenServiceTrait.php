<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;
use DateTimeImmutable;
use Exception;
use Nette\Utils\Random;
use ReflectionException;

trait OnetimeTokenServiceTrait
{
	public function __construct(protected EntityManager $em, protected OnetimeTokenQueryFactory $onetimeTokenQueryFactory)
	{
	}

	/**
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function generateToken(OnetimeTokenType $type, DateTimeImmutable $validUntil, ?string $objectClass = null, ?int $objectId = null, ?int $length = 32, ?string $charList = 'a-zA-Z0-9'): OnetimeToken
	{
		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->em->findEntityClassByInterface(OnetimeToken::class));
		$onetimeToken
			->setType($type->value)
			->setValidUntil($validUntil)
			->setObjectClass($objectClass)
			->setObjectId($objectId)
			->setToken(Random::generate(32, 'a-zA-Z0-9'))
			->setIpAddress($_SERVER['REMOTE_ADDR']);
		$this->em->persist($onetimeToken);
		$this->em->flush();
		return $onetimeToken;
	}

	public function findToken(OnetimeTokenType $type, string $token): ?OnetimeToken
	{
		return $this->onetimeTokenQueryFactory->create()
			->byIsValid()
			->byType($type->value)
			->byToken($token)
			->fetchOneOrNull();
	}
}