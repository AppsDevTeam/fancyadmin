<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\ApiKey;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\ApiKeyQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\ApiKey;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;

/**
 * Formulář API klíče.
 *
 * Účet se do formuláře přidává automaticky (BaseFormTrait::onBeforeInitForm),
 * pokud má identita k dispozici více účtů; jinak ho při persistu doplní AccountFieldListener.
 */
trait ApiKeyFormTrait
{
	use ApiKeyQueryFactoryInject;
	use EntityManagerInject;

	public function initForm(Form $form): void
	{
		$this->addApiKeyFields($form);

		$form->addSubmit('submit', 'fcadmin.presenters.apiKeys.form.submit');
	}

	/**
	 * Pole klíče bez submitu - projekt, který si přidává vlastní pole, přepíše initForm(),
	 * zavolá tuto metodu, přidá svoje pole a submit až nakonec.
	 */
	protected function addApiKeyFields(Form $form): void
	{
		$form->addText('name', 'fcadmin.presenters.apiKeys.form.name')
			->setRequired('fcadmin.presenters.apiKeys.form.errors.nameRequired');
	}

	/**
	 * Jedinečnost jména v rámci účtu hlídá unikátní index (viz ApiKeyTrait::$accountKey);
	 * tahle kontrola je kvůli hlášce, aby uživatel místo pádu na constraintu viděl,
	 * co má opravit. Závod dvou souběžných požadavků proto řešit nemusí - ten doběhne
	 * až na index.
	 */
	public function validateForm(?ApiKey $entity, array $inputs, Form $form): void
	{
		$query = $this->_apiKeyQueryFactory->create()
			->disableSecurityFilter()
			->byName($inputs['name'])
			->byAccountOrGlobal($this->resolveAccountId($entity, $inputs));

		if ($entity && !$entity->isNew()) {
			$query->byIdNot($entity->getId());
		}

		if ($query->fetch()) {
			$form['name']->addError('fcadmin.presenters.apiKeys.form.errors.nameAlreadyExists');
		}
	}

	/**
	 * Účet, do kterého klíč spadne. Pole ve formuláři je jen když má identita víc účtů
	 * (BaseFormTrait::onBeforeInitForm), jinak ho při persistu doplní AccountFieldListener
	 * z vybraného účtu - a v backoffice, kde vybraný účet není, zůstane klíč globální.
	 * Kontrola musí sáhnout do stejné skupiny, jinak by hlídala něco jiného než index.
	 */
	private function resolveAccountId(?ApiKey $entity, array $inputs): ?int
	{
		if (isset($inputs['account'])) {
			return $inputs['account'] ? (int) $inputs['account'] : null;
		}

		return $entity?->getAccount()?->getId()
			?? $this->securityUser->getIdentity()?->getSelectedAccount()?->getId();
	}

	public function processForm(ApiKey $entity): void
	{
		// klíč vzniká jen při vytvoření, existující se editací nemění
		$rawKey = null;
		if (!$entity->getKey()) {
			$rawKey = ApiKeyHasher::generateRawKey();
			$entity->setKey(ApiKeyHasher::hash($rawKey));
		}

		$this->_em->flush();

		if ($rawKey !== null) {
			// klíč jde uživateli ukázat jen teď, v databázi zůstává pouze otisk
			$this->getPresenter()->flashMessageInfo(
				$this->getTranslator()->translate('fcadmin.presenters.apiKeys.messages.created', ['key' => $rawKey])
			);
		}
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(ApiKey::class);
	}
}
