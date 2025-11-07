<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use DateTimeImmutable;
use Exception;
use ReflectionException;

trait OnetimeTokenServiceTrait
{
	public function __construct(protected EntityManager $em)
	{
	}

	/**
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function generateToken(Identity $identity, DateTimeImmutable $validUntil): OnetimeToken
	{
		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->em->findEntityClassByInterface(OnetimeToken::class));
		$onetimeToken
			->setObjectId($identity->getId())
			->setObjectClass($identity::class)
			->setType(OnetimeToken::TYPE_LOGIN)
			->setIpAddress($_SERVER['REMOTE_ADDR'])
			->setToken($onetimeToken::generateRandomToken())
			->setValidUntil($validUntil);
		$this->em->persist($onetimeToken);
		$this->em->flush();
		return $onetimeToken;
	}
}