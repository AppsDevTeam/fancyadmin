<?php

namespace ADT\FancyAdmin\UI\Components\Forms\AclRole;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\Forms\Form;
use Exception;

/**
 * @property AclRole $entity
 */
trait AclRoleFormTrait
{
	use EntityManagerInject;
	
	/**
	 * @throws Exception
	 */
	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.forms.aclRole.name')
			->setRequired();

		$form->addSubmit('submit', 'fcadmin.forms.aclRole.submit');
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(AclRole::class);
	}

	/**
	 * @throws Exception
	 */
	public function processForm(): void
	{
		$this->em->flush();
	}
}
