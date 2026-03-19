<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Listeners;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

trait SelectAccountListenerTrait
{
	public function __construct(
		protected readonly SecurityUser $securityUser,
		protected readonly FancyAdmin $fancyAdmin,
	) {
	}

	public function getSubscribedEvents(): array
	{
		return [
			Events::onFlush,
		];
	}

	public function onFlushCallback(OnFlushEventArgs $args): void
	{
		if (!$this->securityUser->isLoggedIn()) {
			return;
		}

		$em = $args->getObjectManager();
		$uow = $em->getUnitOfWork();

		$identities = [];

		foreach (array_merge($uow->getScheduledEntityUpdates(), $uow->getScheduledEntityInsertions()) as $entity) {
			if ($entity instanceof Identity) {
				$identities[$entity->getId()] = $entity;
			} elseif ($entity instanceof Profile) {
				$identity = $entity->getIdentity();
				$identities[$identity->getId()] = $identity;
			}
		}

		foreach ($identities as $identity) {
			$selectedAccount = $identity->getSelectedAccount();
			$hasBackoffice = $identity->isAllowed($this->fancyAdmin->getBackofficeAclResource());

			// 1) nemá backoffice přístup a nemá nastavený selectedAccount -> nastav první aktivní profil
			if (!$hasBackoffice && !$selectedAccount) {
				$identity->setSelectedAccount($this->getFirstActiveProfile($identity)?->getAccount());
				$this->entitiesToRecompute[] = $identity;
			}

			// 2) má nastavený selectedAccount, ale odpovídající profil je neaktivní
			if ($selectedAccount) {
				$profileIsActive = false;
				foreach ($identity->getProfiles() as $_profile) {
					if ($_profile->getAccount() === $selectedAccount && $_profile->getIsActive()) {
						$profileIsActive = true;
						break;
					}
				}

				if (!$profileIsActive) {
					if ($hasBackoffice) {
						$identity->setSelectedAccount(null);
					} else {
						$identity->setSelectedAccount($this->getFirstActiveProfile($identity)?->getAccount());
					}
					$this->entitiesToRecompute[] = $identity;
				}
			}
		}
	}

	private function getFirstActiveProfile(Identity $identity): ?Profile
	{
		foreach ($identity->getProfiles() as $_profile) {
			if ($_profile->getIsActive()) {
				return $_profile;
			}
		}

		return null;
	}
}
