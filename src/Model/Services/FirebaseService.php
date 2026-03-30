<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use Doctrine\ORM\NonUniqueResultException;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Nette\Localization\Translator;
use Tracy\Debugger;

abstract class FirebaseService
{
	public function __construct(
		protected string $serviceAccount,
		readonly protected EntityManager $entityManager,
		readonly protected IdentityQueryFactory $userQueryFactory,
		readonly protected Translator $translator,
	) {
	}

	/**
	 * @throws MessagingException
	 * @throws FirebaseException
	 * @throws \ReflectionException
	 * @throws NonUniqueResultException
	 */
	public function sendMessage(string $token, array $body): void
	{
		try {
			(new Factory())
				->withServiceAccount($this->serviceAccount)
				->createMessaging()
				->send(
					CloudMessage::withTarget('token', $token)
						->withData($body)
						->withHighestPossiblePriority()
				);
		} catch (NotFound) {
			$validTokens = [];
			foreach ($this->userQueryFactory->create()->byFirebaseToken($token)->fetch() as $_user) {
				Debugger::log($_user->getId() . ' ' . $token, 'firebase-not-found');
				foreach ($_user->getFirebaseTokens() as $userToken) {
					if ($userToken === $token) {
						continue;
					}

					$validTokens[] = $userToken;
				}
				$_user->setFirebaseTokens($validTokens);
			}
			$this->entityManager->flush();
		}
	}
}
