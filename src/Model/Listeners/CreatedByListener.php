<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Listeners;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedByInterface;
use App\Model\Security\SecurityUser;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

trait CreatedByListener
{
	public function __construct(
		protected readonly SecurityUser $securityUser,
	) {
	}

	public function getSubscribedEvents(): array
	{
		return [
			Events::prePersist,
			Events::preUpdate,
		];
	}

	public function prePersistCallback(PrePersistEventArgs $args): void
	{
		$entity = $args->getObject();

		if (
			$entity instanceof CreatedByInterface
			||
			($entity instanceof CreatedByNullInterface && $this->securityUser->isLoggedIn())
		) {
			$entity->setCreatedBy($this->securityUser->getIdentity());
		}
	}

	public function preUpdateCallback(PreUpdateEventArgs $args): void
	{
		$entity = $args->getObject();

		if ($entity instanceof UpdatedByInterface) {
			$entity->setUpdatedBy($this->securityUser->getIdentity());
		}
	}
}
