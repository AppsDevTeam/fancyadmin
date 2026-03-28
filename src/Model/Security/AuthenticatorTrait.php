<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineAuthenticator\OTP\Identity;
use ADT\FancyAdmin\Model\FancyAdmin;
use Nette\Security\AuthenticationException;

trait AuthenticatorTrait
{
	protected FancyAdmin $fancyAdmin;

	public function setFancyAdmin(FancyAdmin $fancyAdmin): void
	{
		$this->fancyAdmin = $fancyAdmin;
	}

	protected function validateIdentity(Identity $identity, ?string $context = null, array $metadata = []): void
	{
		/** @var \ADT\FancyAdmin\Model\Entities\Identity $identity */
		if (
			!$identity->isAllowed($this->fancyAdmin->getCustomerAclResource())
			&&
			!$identity->isAllowed($this->fancyAdmin->getBackofficeAclResource())
		) {
			throw new AuthenticationException('Nemáte oprávnění pro přihlášení');
		}
	}

}
