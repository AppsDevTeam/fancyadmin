<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Identity;

use ADT\Forms\StaticContainer;
use App\Model\Entities\Identity;
use App\Model\Entities\SmartCard;
use App\Model\Queries\Factories\AclRoleQueryFactory;
use App\Model\Queries\Factories\BranchQueryFactory;
use App\Model\Queries\Factories\CompanyQueryFactory;
use App\Model\Queries\Factories\WarehouseQueryFactory;
use App\Model\Services\UserService;
use App\UI\Portal\Components\Forms\Base\BaseForm;
use App\UI\Portal\Components\Forms\Base\EntityForm;
use App\UI\Portal\Components\Forms\UserFormTrait;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Exception;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use ADT\Forms\Form;

/**
 * @property Identity $entity
 */
trait IdentityFormTrait
{
	use IdentityFormTrait;

	#[Autowire]
	protected UserService $userService;

	#[Autowire]
	protected CompanyQueryFactory $companyQueryFactory;

	#[Autowire]
	protected AclRoleQueryFactory $aclRoleQueryFactory;

	#[Autowire]
	protected BranchQueryFactory $branchQueryFactory;

	#[Autowire]
	protected WarehouseQueryFactory $warehouseQueryFactory;

	/**
	 * @throws Exception
	 */
	public function initForm(Form $form, ?Identity $identity): void
	{
		$this->initUserForm($form);

		$form->addText('bankAccount', 'app.forms.user.labels.bankAccount');

		$companyRoles = $this->aclRoleQueryFactory->create()->byIsCustomer(true)->fetchPairs();
		$adminRoles = $this->aclRoleQueryFactory->create()->byIsAdmin(true)->fetchPairs();
		$visitorRoles = $this->aclRoleQueryFactory->create()->byIsVisitor(true)->fetchPairs();
		$form->addDynamicContainer(
			'profiles',
			function (StaticContainer $container) use ($identity, $form, $companyRoles, $adminRoles, $visitorRoles) {
				$container->addCheckbox('isActive', 'app.forms.user.labels.isActive');

				$container->addMultiSelect('roles', 'app.forms.user.labels.role', $identity ? $adminRoles + $companyRoles + $visitorRoles : $adminRoles)
					->setRequired();

				$container->addSelect('account', 'app.forms.user.labels.company', $this->companyQueryFactory->create()->disableAccountFilter()->fetchPairs('fullName'))
					->setPrompt('---');

				$container->addMultiSelect('branches', 'app.forms.user.labels.branches', $this->branchQueryFactory->create()->fetchPairs('fullName'));
				$container->addMultiSelect('warehouses', 'app.forms.user.labels.warehouses', $this->warehouseQueryFactory->create()->fetchPairs());
			}
		);

		$form->addGroup();

		// name inputu "smartCards" (manyToMany) jako v entite nefungovalo správně v proccessFormu (= zůstavaly entity v kolekci a nešlo odebrat)
		$form->addAjaxMultiSelect('_smartCards', 'app.forms.user.labels.smartCards', 'smart-card');

		if ($identity && !$identity->isNew()) {
			$smartCards = [];
			foreach ($identity->getSmartCards() as $smartCard) {
				$smartCards[] = $smartCard->getId();
			}
			$form['_smartCards']->setDefaultValue($smartCards);
		}

		$form->addSubmit('chosenCompany')
			->setValidationScope([])
			->onClick[] = function () {
				$this->redrawControl('chosenCompany');
			};
	}

	protected function getProfilesContainer(?Identity $user): StaticContainer
	{
		$profiles = $this->form->getComponentDynamicContainer('profiles');

		if ($profiles->getContainers()) {
			return current($profiles->getContainers());
		}

		if (!$user) {
			$profiles->createNew();
		} else {
			$profiles[0];
		}
		return current($profiles->getContainers());
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function processForm(Identity $identity, ArrayHash $values): void
	{
		foreach ($identity->getSmartCards() as $smartCard) {
			$identity->removeSmartCard($smartCard);
		}
		foreach ($values->_smartCards as $smartCardId) {
			if ($smartCard = $this->em->getRepository(SmartCard::class)->find($smartCardId)) {
				$identity->addSmartCard($smartCard);
			}
		}

		$this->processUserForm($identity);
	}

	protected function getEntityClass(): ?string
	{
		return Identity::class;
	}
}
