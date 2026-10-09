<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Account;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\Forms\Form;
use Exception;

/**
 * Formular uctu. Tridu entity si projekt doplni sam v `getEntityClass()` (vyzaduje ji
 * BaseFormTrait) - knihovna zna jen rozhrani Account a to se instancovat neda.
 *
 * @property Account $entity
 */
trait AccountFormTrait
{
	/**
	 * @throws Exception
	 */
	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.forms.account.name')
			->setRequired();

		$form->addSubmit('submit', 'fcadmin.forms.account.submit');
	}

	/**
	 * @throws Exception
	 */
	public function processForm(): void
	{
		$this->em->flush();
	}
}
