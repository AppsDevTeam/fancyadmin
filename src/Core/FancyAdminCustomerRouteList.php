<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\Routing\Route;
use Doctrine\ORM\NoResultException;
use Nette\Application\BadRequestException;
use Nette\Routing\Route as RouteAlias;

class FancyAdminCustomerRouteList extends FancyAdminRouteList
{
	public function createRoute(string $mask, $metadata = [], int $flags = 0): Route
	{
		if (is_array($metadata)) {
			$metadata = array_merge(
				[
					'selectedAccount' => [
						RouteAlias::FilterIn => function (string $selectedAccount) {
							// nette vse natvrdo pretypovava na string
							$selectedAccount = (int) $selectedAccount;

							if ($this->securityUser->isLoggedIn()) {
								try {
									if ($this->securityUser->getIdentity()->getSelectedAccount()?->getId() !== $selectedAccount) {
										/** @var Account $account */
										$account = $this->accountQueryFactory->create()->disableAccountFilter()->byId($selectedAccount)->fetchOne();
										$this->securityUser->getIdentity()->setSelectedAccount($account);
										$this->em->flush();
									}
								} catch (NoResultException) {
									throw new BadRequestException();
								}
							}
							return $selectedAccount;
						},
					],
				],
				$metadata
			);
		}

		return parent::createRoute('<selectedAccount \d+>/' . $mask, $metadata, $flags);
	}
}
