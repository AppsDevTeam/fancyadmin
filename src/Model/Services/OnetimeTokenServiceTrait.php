<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineComponents\Entities\Entity;
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
	public function saveToken(OnetimeTokenType $type, DateTimeImmutable $validUntil, ?Entity $entity = null, ?string $token = null): OnetimeToken
	{
		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->em->findEntityClassByInterface(OnetimeToken::class));
		$onetimeToken
			->setType($type->value)
			->setValidUntil($validUntil)
			->setObjectClass($entity ? $entity::class : null)
			->setObjectId($entity?->getId())
			->setToken($token ?: Random::generate(32, 'a-zA-Z0-9'))
			->setIpAddress($_SERVER['REMOTE_ADDR']);
		$this->em->persist($onetimeToken);
		$this->em->flush();
		return $onetimeToken;
	}

	public function findToken(OnetimeTokenType $type, string $token): ?OnetimeToken
	{
		$onetimeToken = $this->onetimeTokenQueryFactory->create()
			->byIsValid()
			->byType($type->value)
			->byToken($token)
			->fetchOneOrNull();

		$onetimeToken->setUsedAt(new DateTimeImmutable());
		$this->em->flush();
		
		return $onetimeToken;
	}
}