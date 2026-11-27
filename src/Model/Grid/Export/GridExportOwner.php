<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use ADT\DoctrineComponents\EntityManager;
use ADT\Exporter\Model\Entities\Export;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;
use ADT\FancyAdmin\Model\Security\SecurityUser;

/** Export patří tomu, kdo ho zadal - stáhnout ho smí jen on (ExportsPresenterTrait). Callback $onExport exporteru. */
final class GridExportOwner
{
	public function __construct(
		private readonly SecurityUser $securityUser,
		private readonly EntityManager $em,
	) {
	}

	public function stamp(Export $export): void
	{
		if (!$export instanceof CreatedByNullableInterface || $export->getCreatedBy() || !$this->securityUser->isLoggedIn()) {
			return;
		}

		$identity = $this->securityUser->getIdentity();
		if ($identity instanceof Identity) {
			// Identita z přihlášení může být po EntityManager::clear() odpojená
			$export->setCreatedBy($this->em->contains($identity) ? $identity : $this->em->getReference($identity::class, $identity->getId()));
		}
	}
}
