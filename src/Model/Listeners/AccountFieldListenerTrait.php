<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Listeners;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

trait AccountFieldListenerTrait
{
	public function __construct(
		protected readonly \ADT\FancyAdmin\Model\Security\SecurityUser $securityUser,
	) {
	}

	public function getSubscribedEvents(): array
	{
		return [
			Events::prePersist,
		];
	}

	public function prePersistCallback(PrePersistEventArgs $args): void
	{
		bd($this->securityUser->getIdentity());
		$entity = $args->getObject();
		$identity = $this->securityUser->getIdentity();

		if (!$identity || !method_exists($entity, 'setAccount')) {
			return;
		}

		$em = $args->getObjectManager();
		$classMetadata = $em->getClassMetadata(get_class($entity));

		if (!$classMetadata->hasAssociation('account')) {
			return;
		}

		$accountValue = $classMetadata->getFieldValue($entity, 'account');

		if (!$accountValue) {
			$selectedAccount = $identity->getSelectedAccount();
			if ($selectedAccount) {
				$entity->setAccount($selectedAccount);
			}
		}
	}
}
