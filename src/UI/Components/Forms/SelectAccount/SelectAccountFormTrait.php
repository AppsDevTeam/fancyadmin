<?php

namespace ADT\FancyAdmin\UI\Components\Forms\SelectAccount;

use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\Forms\Form;
use Contributte\Translation\Exceptions\InvalidArgument;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Nette\Application\UI\InvalidLinkException;
use ReflectionException;

trait SelectAccountFormTrait
{
	use FormTrait;
	use AccountQueryFactoryInject;
	use SecurityUserInject;
	use FancyAdminInject;
	use TranslatorInject;
	use EntityManagerInject;

	const string SETTINGS = 'settings';

	/**
	 * @throws ReflectionException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 */
	public function initForm(Form $form): void
	{
		if ($this->_securityUser->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			$usersCompanies = $this->_accountQueryFactory->create()
				->disableAccountFilter()
				->fetch();
		} else {
			$usersCompanies = $this->_securityUser->getIdentity()->getAccounts();
		}
		$usersCompanyPairs = [];
		foreach ($usersCompanies as $_company) {
			$usersCompanyPairs[$_company->getId()] = $_company->getFullName();
		}
		asort($usersCompanyPairs);
		if ($this->_securityUser->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			//pridani option pro presmerovani do settings, respektive pro odnastaveni spolcnosi pokud ma user global companies
			$usersCompanyPairs[self::SETTINGS] = $this->_translator->translate('app.forms.systemSelectCompany.options.admin');
		}
		$form->addSelect('account', '', $usersCompanyPairs)
			->setDefaultValue($this->_securityUser->getIdentity()->getSelectedAccount()?->getId() ?: self::SETTINGS)
			->setHtmlAttribute('data-adt-select2', [
				'dropdownCssClass' => 'select2-primary-dropdown',
			]);

		$form->addSubmit("submit", '')
			->setHtmlAttribute('class', 'superUltraSecretSubmit');
	}

	/**
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 * @throws Exception
	 */
	public function processForm(array $values): never
	{
		if ($values['account'] === 'settings') {
			// Pokud mame cloveka s global companies, tam se nastavi company jako null
			$this->_securityUser->getIdentity()->setSelectedAccount(null);
			$this->_em->flush();
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute(), ['do' => 'redrawBody', 'selectedAccount' => null]);
		} else {
			// Pripad kdy je vybrana spolecnost -> nastavujeme spolecnost
			$this->_securityUser->getIdentity()->setSelectedAccount($this->_accountQueryFactory->create()->disableAccountFilter()->byId($values['account'])->fetchOne());
			$this->_em->flush();
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute(), ['do' => 'redrawBody', 'selectedAccount' => $this->_securityUser->getIdentity()->getSelectedAccount()?->getId()]);
		}
	}

	protected function getEntityClass(): ?string
	{
		return null;
	}
}
