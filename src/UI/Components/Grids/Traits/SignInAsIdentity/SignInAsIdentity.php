<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity;

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use App\Model\Entities\OnetimeToken;
use Nette\Utils\Random;

trait SignInAsIdentity
{
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
		$token = new OnetimeToken()
			->setObjectId($identity->getId())
			->setValidUntil(new \DateTimeImmutable('+15 minutes'))
			->setIpAddress($_SERVER['REMOTE_ADDR'])
			->setType(OnetimeToken::TYPE_LOGIN)
			->setToken(Random::generate(32));

		$this->_em->persist($token);
		$this->_em->flush();

		$this->getPresenter()->payload->signAsIdentityLink = $this->getPresenter()->link('//Sign:token', [
			'email' => $identity->getEmail(),
			'token' => $token->getToken(),
			'skipPasswordRecovery' => 1,
		]);

		$this->getPresenter()->sendPayload();
	}
}
