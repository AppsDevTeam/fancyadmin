<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use Nette\Utils\Random;

trait SignInAsIdentity
{
	use EntityManagerInject;

	public function injectSignInAsIdentity(): void
	{
		$this->onAnchor[] = function () {
			if ($this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_IDENTITIES)) {
				$this['grid']->addHtmlDataAttribute('data-adt-portal-components-grids-traits-sign-in-as-identity');
				$this['grid']
					->addAction('signInAsIdentity', '')
					->setRenderer(function ($item) {
						return '
							<a href="javascript:void(0);" class="noajax"
								data-button-text="' . $this->_translator->translate('fcadmin.grids.user.actions.signAsIdentityOpenInIncognitoWindow') . '"
								data-sign-in-as-identity-url="?do=' . $this->name . '-signInAsIdentity&' . $this->name . '-id=' . $item->getId() . '
							">
								<span class="fa fa-sign-in"></span>&nbsp;' . $this->_translator->translate('fcadmin.grids.user.actions.signAsIdentity') . '
							</a>
						';
					});
			}
		};
	}

	public function handleSignInAsIdentity(int $id): void
	{
		if (
			!$this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_IDENTITIES)
			||
			!($identity = $this->_identityQueryFactory->create()->byId($id)->fetchOneOrNull())
		) {
			$this->error();
		}
		$this->createSignAsIdentityLink($identity);
	}

	protected function createSignAsIdentityLink(Identity $identity): never
	{
		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->_em->findEntityClassByInterface(OnetimeToken::class);
		$onetimeToken
			->setObjectId($identity->getId())
			->setObjectClass($identity::class)
			->setValidUntil(new \DateTimeImmutable('+15 minutes'))
			->setIpAddress($_SERVER['REMOTE_ADDR'])
			->setType(OnetimeToken::TYPE_LOGIN)
			->setToken(Random::generate(32));

		$this->_em->persist($onetimeToken);
		$this->_em->flush();

		$this->getPresenter()->payload->signAsIdentityLink = $this->getPresenter()->link('//Sign:token', [
			'token' => $onetimeToken->getToken(),
			'skipPasswordRecovery' => 1,
		]);

		$this->getPresenter()->sendPayload();
	}
}
