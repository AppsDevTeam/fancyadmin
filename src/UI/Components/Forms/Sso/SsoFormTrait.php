<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Sso;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\UI\Components\Forms\IsActiveFormField;
use ADT\Forms\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Validators;

trait SsoFormTrait
{
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;
	use IsActiveFormField;

	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.presenters.sso.form.name')
			->setRequired('fcadmin.presenters.sso.form.errors.nameRequired');

		$form->addText('realm', 'fcadmin.presenters.sso.form.realm')
			->setRequired('fcadmin.presenters.sso.form.errors.realmRequired');

		// Musí to být absolutní URL. Jaký host, to je věc administrátora - tohle neomezuje
		// kam smí mířit, jen odmítá hodnotu, která jako URL nedává smysl, aby spadla tady
		// s chybou u pole místo tichého přeskočení za běhu (Validators::isUrl
		// v SignPresenterTrait::attemptSilentSso).
		//
		// Zámerne NE Form::URL: to hodnotu normalizuje - z 'test' udělá 'https://test',
		// samo to zapíše do pole a pustí dál. Vznikla by tak syntakticky platná, ale
		// nefunkční konfigurace, která projde i tou runtime pojistkou.
		$form->addText('baseUrl', 'fcadmin.presenters.sso.form.baseUrl')
			->setRequired('fcadmin.presenters.sso.form.errors.baseUrlRequired')
			->addRule($this->isAbsoluteUrl(...), 'fcadmin.presenters.sso.form.errors.baseUrlInvalid');

		$form->addText('hostUrl', 'fcadmin.presenters.sso.form.hostUrl')
			->setRequired('fcadmin.presenters.sso.form.errors.hostUrlRequired')
			->addRule($this->isAbsoluteUrl(...), 'fcadmin.presenters.sso.form.errors.hostUrlInvalid');

		$form->addText('clientId', 'fcadmin.presenters.sso.form.clientId')
			->setRequired('fcadmin.presenters.sso.form.errors.clientIdRequired');

		$form->addText('clientSecret', 'fcadmin.presenters.sso.form.clientSecret')
			->setRequired('fcadmin.presenters.sso.form.errors.clientSecretRequired');

		$form->addText('frontendClientId', 'fcadmin.presenters.sso.form.frontendClientId')
			->setRequired('fcadmin.presenters.sso.form.errors.frontendClientIdRequired');

		$form->addSelect('defaultRole', 'fcadmin.presenters.sso.form.defaultRole', $this->_aclRoleQueryFactory->create()->fetchPairs('name'))
			->setPrompt('---');

		// Neaktivní instance se nezapojí do přihlašování (viz KeycloakManager::getAllSsoRecords),
		// aniž by se musela smazat.
		$this->addIsActiveField($form, 'fcadmin.presenters.sso.form.isActive');

		$form->addSubmit('submit', 'fcadmin.presenters.sso.form.submit');
	}

	public function processForm(Sso $entity): void
	{
		$this->_em->flush();
	}

	/**
	 * Absolutní http(s) URL, bez normalizace hodnoty.
	 * Validators::isUrl vyžaduje schéma, takže odmítne 'test', 'kc.example.com'
	 * i 'javascript:'; jednoslovný host ('https://keycloak') naopak projde,
	 * protože na vnitřní síti je legitimní.
	 */
	private function isAbsoluteUrl(TextInput $control): bool
	{
		return Validators::isUrl((string) $control->getValue());
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Sso::class);
	}
}
