<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Sso;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\Forms\Form;

trait SsoFormTrait
{
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;

	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.presenters.sso.form.name')
			->setRequired('fcadmin.presenters.sso.form.errors.nameRequired');

		$form->addText('realm', 'fcadmin.presenters.sso.form.realm')
			->setRequired('fcadmin.presenters.sso.form.errors.realmRequired');

		$form->addText('baseUrl', 'fcadmin.presenters.sso.form.baseUrl')
			->setRequired('fcadmin.presenters.sso.form.errors.baseUrlRequired');

		$form->addText('hostUrl', 'fcadmin.presenters.sso.form.hostUrl')
			->setRequired('fcadmin.presenters.sso.form.errors.hostUrlRequired');

		$form->addText('clientId', 'fcadmin.presenters.sso.form.clientId')
			->setRequired('fcadmin.presenters.sso.form.errors.clientIdRequired');

		$form->addText('clientSecret', 'fcadmin.presenters.sso.form.clientSecret')
			->setRequired('fcadmin.presenters.sso.form.errors.clientSecretRequired');

		$form->addText('frontendClientId', 'fcadmin.presenters.sso.form.frontendClientId')
			->setRequired('fcadmin.presenters.sso.form.errors.frontendClientIdRequired');

		$form->addSelect('defaultRole', 'fcadmin.presenters.sso.form.defaultRole', $this->_aclRoleQueryFactory->create()->fetchPairs('name'))
			->setPrompt('---');

		$form->addSubmit('submit', 'fcadmin.presenters.sso.form.submit');
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Sso::class);
	}
}
