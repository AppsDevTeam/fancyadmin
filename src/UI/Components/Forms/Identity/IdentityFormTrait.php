<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Identity;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Identity;
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
		$this->addIdentityFields($form);

		$this->addRoles($form, $this->getIdentityRoles());

		$form->addDynamicContainer(
			'profiles',
			function (StaticContainer $container) use ($identity, $form) {
				$container->addCheckbox('isActive', 'app.forms.user.labels.isActive');
				$this->addRoles($container, $this->getProfileRoles());
				$container->addSelect('account', 'app.forms.user.labels.company', $this->_accountQueryFactory->create()->disableAccountFilter()->fetchPairs('fullName'))
					->setPrompt('---');
				$container->addSection(function () use ($form, $container) {
					$form->mapToForm();
					$this->addProfileFields($container);

				}, 'account', watchForRedraw: [$container['account']]);
			}
		);

		$form->mapToForm();

		$roleControls = [$form['roles'], $form['profiles'][DynamicContainer::NEW_PREFIX]['roles']];
		foreach ($form['profiles']->getComponents() as $_profileContainer) {
			$roleControls[] = $_profileContainer['roles'];
		}
		$form->addSection(function () use ($form, $identity) {
			$roleIds = $form['roles']->getValue();
			foreach ($form['profiles']->getComponents() as $_profileContainer) {
				$roleIds = array_merge($roleIds, $_profileContainer['roles']->getValue());
			}
			$roles = $this->_aclRoleQueryFactory->create()->byId($roleIds)->fetch();
			$this->addRoleBasedFields($form, $identity, $roles);
		}, name: 'roleBasedFields', watchForRedraw: $roleControls);

		$form->addSubmit('submit', 'app.forms.user.labels.submit');
	}

	protected function getEntityClass(): ?string
	{
		return Identity::class;
	}

	protected function addRoleBasedFields(Form $form, ?Identity $identity, array $roles): void
	{
	}
}
