<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Tablet;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Tablet;

/**
 * Formulář tabletu - jen název, pod kterým ho obsluha vybírá při odeslání formuláře.
 * Spárování a odpojení jsou akce v gridu (TabletsPresenterTrait).
 */
trait TabletFormTrait
{
	use EntityManagerInject;

	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.tablets.form.name')
			->setRequired('fcadmin.tablets.form.errors.nameRequired')
			->setMaxLength(100)
			->setHtmlAttribute('placeholder', 'fcadmin.tablets.form.namePlaceholder');

		$form->addSubmit('submit', 'fcadmin.tablets.form.submit');
	}

	public function processForm(Tablet $entity): void
	{
		/** @var Tablet|null $existing */
		$existing = $this->_em->getRepository($this->getEntityClass())->findOneBy(['name' => $entity->getName()]);
		if ($existing && $existing !== $entity) {
			$this->form->addError($this->getTranslator()->translate('fcadmin.tablets.form.errors.nameExists', ['name' => $entity->getName()]));
			return;
		}

		$this->_em->flush();
	}

	protected function createEntity(): Tablet
	{
		$class = $this->getEntityClass();

		return new $class();
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Tablet::class);
	}
}
