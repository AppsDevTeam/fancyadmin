<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Account;

use ADT\Forms\Form;
use App\Model\Entities\Account;
use Exception;

/**
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

	protected function getEntityClass(): ?string
	{
		return Account::class;
	}

	/**
	 * @throws Exception
	 */
	public function processForm(): void
	{
		$this->em->flush();
	}
}
