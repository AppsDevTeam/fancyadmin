<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;

class SessionExpirationCallback
{
	public function __construct(
		private ConfigurationQueryFactory $configurationQueryFactory,
		private FancyAdmin $fancyAdmin,
	) {}

	public function __invoke(DoctrineAuthenticatorIdentity $identity): ?string
	{
		if (!$identity instanceof Identity) {
			return null;
		}

		if ($identity->isAdmin()) {
			$policyKey = 'password.policy.admin';
		} elseif ($identity->isAllowed($this->fancyAdmin->getBackofficeAclResource())) {
			$policyKey = 'password.policy.backoffice';
		} else {
			return null;
		}

		$config = $this->configurationQueryFactory->create()->disableSecurityFilter()->disableAccountFilter()->byKey($policyKey)->fetchOneOrNull();
		if (!$config) {
			return null;
		}

		$policy = json_decode($config->getValue(), true);
		if (!($policy['enabled'] ?? false)) {
			return null;
		}

		$minutes = $policy['sessionExpirationMinutes'] ?? null;
		if ($minutes === null || $minutes <= 0) {
			return null;
		}

		return $minutes . ' minutes';
	}
}
