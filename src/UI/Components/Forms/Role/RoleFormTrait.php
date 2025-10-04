<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Role;

use ADT\Forms\Form;
use App\Model\Entities\AclRole;
use Exception;

/**
 * @property AclRole $entity
 */
trait RoleFormTrait
{
	/**
	 * @throws Exception
	 */
	public function initForm(Form $form): void
	{
		$form->addText('name', 'Name')
			->setRequired();

		$form->addSubmit('submit', 'Submit');
	}

	protected function getEntityClass(): ?string
	{
		return AclRole::class;
	}

	/**
	 * @throws Exception
	 */
	public function processForm(): void
	{
		$this->em->flush();
	}
}
