<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Listeners;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\SoftDeleteableInterface;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Doctrine\Persistence\Event\LifecycleEventArgs;

trait SoftDeleteableListenerTrait
{
	public function __construct(
		protected readonly SecurityUser $securityUser,
	) {
	}

	public function getSubscribedEvents(): array
	{
		return [
			\Gedmo\SoftDeleteable\SoftDeleteableListener::PRE_SOFT_DELETE,
		];
	}

	public function preSoftDelete(LifecycleEventArgs $args): void
	{
		$entity = $args->getObject();

		if (!$entity instanceof SoftDeleteableInterface) {
			return;
		}

		$entity->setIsDeleted();
		$this->em->getUnitOfWork()->scheduleExtraUpdate($entity, [
			'isDeleted' => [0, $entity->getIsDeleted()],
		]);

		if ($this->securityUser->isLoggedIn()) {
			$identityClass = $this->em->findEntityClassByInterface(Identity::class);
			$user = $this->em->getRepository($identityClass)->find($this->securityUser->getId());
			$this->em->getUnitOfWork()->scheduleExtraUpdate($entity, [
				'deletedBy' => [$entity->getDeletedBy(), $user],
			]);
		}

		$this->entitiesToCompute[] = $entity;
	}
}
