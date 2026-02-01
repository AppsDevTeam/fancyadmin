<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity;

use ADT\FancyAdmin\DI\Injects\OnetimeTokenServiceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use ADT\FancyAdmin\Model\Services\OnetimeTokenTypeEnum;
use ADT\FancyAdmin\UI\Components\ControlTrait;

trait SignInAsIdentity
{
	use ControlTrait;
	use OnetimeTokenServiceInject;
	use SecurityUserInject;

	public function injectSignInAsIdentity(): void
	{
		$this->onAnchor[] = function () {
			if ($this->_securityUser->isAdmin()) {
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
			!$this->_securityUser->isAdmin()
			||
			!(
				/** @var HasIdentity $hasIdentity */
				$hasIdentity = $this->createQueryObject()->byId($id)->fetchOneOrNull()
			)
		) {
			$this->error();
		}
		$this->createSignAsIdentityLink($hasIdentity->getIdentity());
	}

	protected function createSignAsIdentityLink(Identity $identity): never
	{
		$token = $this->_onetimeTokenService->saveToken(OnetimeTokenTypeEnum::LOGIN, new \DateTimeImmutable('+15 minutes'), $identity);

		$this->getPresenter()->payload->signAsIdentityLink = $this->getPresenter()->link('//Home:', [
			'token' => $token->getToken(),
		]);

		$this->getPresenter()->sendPayload();
	}
}
