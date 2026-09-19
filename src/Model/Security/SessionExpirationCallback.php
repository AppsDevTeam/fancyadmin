<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\FancyAdmin\Model\Entities\Identity;

/**
 * Expirace session je soucasti politiky hesel, kterou nese role - viz PasswordPolicy.
 */
class SessionExpirationCallback
{
	public function __invoke(DoctrineAuthenticatorIdentity $identity): ?string
	{
		if (!$identity instanceof Identity) {
			return null;
		}

		if (!$policy = PasswordPolicy::strictestOf($identity->getRoles())) {
			return null;
		}

		$minutes = $policy->sessionExpirationMinutes;
		if ($minutes === null || $minutes <= 0) {
			return null;
		}

		return $minutes . ' minutes';
	}
}
