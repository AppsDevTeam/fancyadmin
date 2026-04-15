<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\AnonymizeIdentity;

use ADT\DoctrineAuthenticator\OTP\OnetimeTokenService;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenServiceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use ADT\FancyAdmin\Model\Services\OnetimeTokenTypeEnum;
use ADT\FancyAdmin\UI\Components\ControlTrait;

trait AnonymizeIdentity
{
	use ControlTrait;
	use SecurityUserInject;

	public function injectAnonymize(): void
	{
		$this->onAnchor[] = function () {
			if ($this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_IDENTITIES_ANONYMIZE)) {
				$this['grid']->addAction('anonymize', 'Anonymizovat')
					->setRenderer(function (Identity $identity) {
						echo '<a href="' . $this->link('anonymize!', $identity->getId()) . '" class="ajax" data-datagrid-confirm="' . $this->_translator->translate('fcadmin.grids.user.confirms.anonymize') . '">
						<span class="fa fa-face-disguise"></span>
						' . $this->_translator->translate('fcadmin.grids.user.actions.anonymize') . '
					</a>';
					});
			}
		};
	}

	public function handleAnonymize(int $identityId): void
	{
		if (!$this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_IDENTITIES_ANONYMIZE)) {
			$this->getPresenter()->error();
		}

		/** @var Identity $identity */
		if (!$identity = $this->createQueryObject()->byId($identityId)->fetchOneOrNull()) {
			$this->getPresenter()->error();
		}

		$identity->setAnonymizedAt(new \DateTimeImmutable());
		$identity->setAnonymizedBy($this->_securityUser->getIdentity());
		$identity->setLastName(mb_substr($identity->getLastName(), 0, 1) . '.');
		$identity->setEmail(null);
		$identity->setPhoneNumber(null);
		$identity->setUsername(null);
		$identity->setPassword(null);
		$identity->setIsActive(false);
		foreach ($identity->getProfiles() as $_profile) {
			$_profile->setIsActive(false);
		}

		$this->_em->flush();
	}
}
