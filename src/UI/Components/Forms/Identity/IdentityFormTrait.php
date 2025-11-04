<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Identity;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\UI\Components\Forms\IdentityProfileFormTrait;
use ADT\Forms\DynamicContainer;
use ADT\Forms\StaticContainer;
use Exception;
use Nette\Forms\Container;

/**
 * @property Identity $entity
 */
trait IdentityFormTrait
{
	use IdentityProfileFormTrait;
	use AccountQueryFactoryInject;
	use AclRoleQueryFactoryInject;

	/**
	 * @throws Exception
	 */
	public function initForm(Form $form, ?Identity $identity): void
	{
		$this->addFormFields($form, $identity, isProfile: false);
	}

	protected function processForm(Identity $identity, array $values): void
	{
		$this->processUserForm($identity);
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Identity::class);
	}

	protected function addRoleBasedFields(Form $form, ?Identity $identity, array $roles): void
	{
	}

	public function isAllowedToEdit(): true
	{
		return true;
	}
}
