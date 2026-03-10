<?php

namespace ADT\FancyAdmin\UI\Components\Forms\AclRole;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\Forms\Form;
use Exception;

/**
 * @property AclRole $entity
 */
trait AclRoleFormTrait
{
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;
	use FancyAdminInject;
	
	/**
	 * @throws Exception
	 */
	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.forms.aclRole.name')
			->setRequired();

		$form->addSelect('type', 'fcadmin.forms.aclRole.type', [
				AclRoleTypeEnum::IDENTITY->value => 'fcadmin.forms.aclRole.types.identity',
				AclRoleTypeEnum::PROFILE->value => 'fcadmin.forms.aclRole.types.profile',
			])
			->setRequired();

		$form->addSubmit('submit', 'fcadmin.forms.aclRole.submit');
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(AclRole::class);
	}

	public function validateForm(?AclRole $entity, array $inputs, Form $form): void
	{
		$query = $this->_aclRoleQueryFactory->create()
			->disableSecurityFilter()
			->byName($inputs['name']);

		if ($entity && !$entity->isNew()) {
			$query->byIdNot($entity->getId());
		}

		if ($query->fetch()) {
			$form['name']->addError('fcadmin.forms.aclRole.errors.nameAlreadyExists');
		}
	}

	/**
	 * @throws Exception
	 */
	public function processForm(AclRole $entity): void
	{
		if ($entity->isNew()) {
			$entity->setContext($this->_fancyAdmin->getContext());;
		}

		$this->em->flush();
	}
}
