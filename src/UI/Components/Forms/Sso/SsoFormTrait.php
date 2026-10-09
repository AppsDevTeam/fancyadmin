<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Sso;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SsoQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\UI\Components\Forms\IsActiveFormField;
use ADT\Forms\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Validators;

trait SsoFormTrait
{
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;
	// Kvůli allowlistu hostů u pole hostUrl. Bez toho je $this->_fancyAdmin
	// nedeklarovaná property a formulář spadne hned při sestavení.
	use FancyAdminInject;
	use IsActiveFormField;
	// Kvůli kontrole unikátnosti názvu ve validateForm().
	use SsoQueryFactoryInject;

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
			->addRule($this->isAbsoluteUrl(...), 'fcadmin.presenters.sso.form.errors.hostUrlInvalid')
			->addRule(
				fn (TextInput $control) => $this->_fancyAdmin->getSsoHostAllowlist()->allows((string) $control->getValue()),
				'fcadmin.presenters.sso.form.errors.hostUrlNotAllowed',
				implode(', ', $this->_fancyAdmin->getSsoHostAllowlist()->getHosts()),
			);

		$form->addText('clientId', 'fcadmin.presenters.sso.form.clientId')
			->setRequired('fcadmin.presenters.sso.form.errors.clientIdRequired');

		// renderValue: false - hodnota by se jinak vypsala do atributu value, takze by
		// client secret videl kazdy, kdo otevre zdroj stranky (nalez WEB-SSO-01).
		$clientSecret = $form->addPasswordReveal('clientSecret', false, 'fcadmin.presenters.sso.form.clientSecret');

		if ($this->getEntity()?->isNew() ?? true) {
			$clientSecret->setRequired('fcadmin.presenters.sso.form.errors.clientSecretRequired');
		} else {
			// Pole se nevykresluje s hodnotou, takze prazdne znamena "nemenit" - viz
			// processForm(). Povinnost by jinak nutila secret opsat pri kazde uprave.
			$clientSecret->setHtmlAttribute('placeholder', 'fcadmin.presenters.sso.form.clientSecretKeep');
		}

		$form->addText('frontendClientId', 'fcadmin.presenters.sso.form.frontendClientId')
			->setRequired('fcadmin.presenters.sso.form.errors.frontendClientIdRequired');

		$form->addSelect('defaultRole', 'fcadmin.presenters.sso.form.defaultRole', $this->_aclRoleQueryFactory->create()->fetchPairs('name'))
			->setPrompt('---');

		// Neaktivní instance se nezapojí do přihlašování (viz KeycloakManager::getAllSsoRecords),
		// aniž by se musela smazat.
		$this->addIsActiveField($form, 'fcadmin.presenters.sso.form.isActive');

		$form->addSubmit('submit', 'fcadmin.presenters.sso.form.submit');
	}

	/**
	 * Název má unique index (SsoTrait::$name), takže bez této kontroly spadne duplicita
	 * až na flushi v processForm() jako UniqueConstraintViolationException - uživatel
	 * dostane chybu 500 místo hlášky u pole.
	 *
	 * Oba filtry se vypínají schválně: index je globální, takže kdyby validace viděla jen
	 * část tabulky, kolidující název by jí prošel a flush by spadl stejně jako předtím.
	 * Stejný důvod jako v AclRoleFormTrait::validateForm().
	 */
	public function validateForm(?Sso $entity, array $inputs, Form $form): void
	{
		$query = $this->_ssoQueryFactory->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byName($inputs['name']);

		if ($entity && !$entity->isNew()) {
			$query->byIdNot($entity->getId());
		}

		if ($query->fetch()) {
			$form['name']->addError('fcadmin.presenters.sso.form.errors.nameAlreadyExists');
		}
	}

	public function processForm(Sso $entity): void
	{
		// Prazdny secret u ulozene instance znamena "nemenit". Formular ho do pole
		// nevypisuje, takze bez teto vetve by kazde ulozeni secret smazalo.
		if (!$entity->isNew() && trim($entity->getClientSecret()) === '') {
			$original = $this->_em->getUnitOfWork()->getOriginalEntityData($entity);
			$entity->setClientSecret($original['clientSecret'] ?? '');
		}

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
