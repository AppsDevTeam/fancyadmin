<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\PersonalData;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Identity;

trait PersonalDataFormTrait
{
	use EntityManagerInject;

	public function initForm(Form $form): void
	{
		$form->addText('firstName', 'fcadmin.forms.personalData.labels.firstName')
			->setRequired('fcadmin.forms.personalData.errors.required');

		$form->addText('lastName', 'fcadmin.forms.personalData.labels.lastName')
			->setRequired('fcadmin.forms.personalData.errors.required');

		$form->addPhoneNumber('phoneNumber', 'fcadmin.forms.personalData.labels.phoneNumber', 'fcadmin.forms.personalData.errors.phoneNumber');

		$form->addSubmit('submit', 'fcadmin.forms.personalData.labels.submit');
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Identity::class);
	}

	public function processForm(): void
	{
		$this->em->flush();
	}
}
