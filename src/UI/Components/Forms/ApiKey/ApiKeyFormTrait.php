<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\ApiKey;

use ADT\DoctrineForms\Form;
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
