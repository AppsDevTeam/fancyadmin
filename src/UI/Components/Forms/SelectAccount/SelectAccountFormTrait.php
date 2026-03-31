<?php

namespace ADT\FancyAdmin\UI\Components\Forms\SelectAccount;

use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Components\Forms\FormTrait;
use ADT\Forms\Form;
use Contributte\Translation\Exceptions\InvalidArgument;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Nette\Application\UI\InvalidLinkException;
use Nette\Forms\Controls\SubmitButton;
use ReflectionException;

trait SelectAccountFormTrait
{
	use ControlTrait;
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
		$form->addSelect('account', '', $this->getAccountPairs())
			->setDefaultValue($this->_securityUser->getIdentity()->getSelectedAccount()?->getId() ?: self::SETTINGS)
			->setHtmlAttribute('class', 'primary-select')
			->setHtmlAttribute('data-adt-select2', [
				'dropdownCssClass' => 'select2-primary-dropdown',
			]);

		$form->watchForSubmit($form['account']);
	}
	
	protected function getAccountPairs(): array
	{
		if ($this->_securityUser->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			$accounts = $this->createBackofficeAccountQuery()
				->fetch();
		} else {
			$accounts = array_merge($this->_securityUser->getIdentity()->getAccounts(), $this->_securityUser->getIdentity()->getSubaccounts());
		}

		$accountPairs = [];
		foreach ($accounts as $_account) {
			$accountPairs[$_account->getId()] = $this->getAccountName($_account);
		}
		asort($accountPairs);
		if ($this->_securityUser->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			//pridani option pro presmerovani do settings, respektive pro odnastaveni spolcnosi pokud ma user global companies
			$accountPairs[self::SETTINGS] = $this->_translator->translate('fcadmin.forms.systemSelectCompany.options.admin');
		}
		return $accountPairs;
	}
	
	protected function createBackofficeAccountQuery()
	{
		return $this->_accountQueryFactory->create()
			->disableAccountFilter();
	}

	protected function getAccountName(Account $account): string
	{
		return $account->getName();
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
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute(), ['selectedAccount' => null]);
		} else {
			// Pripad kdy je vybrana spolecnost -> nastavujeme spolecnost
			$this->_securityUser->getIdentity()->setSelectedAccount($this->_accountQueryFactory->create()->disableAccountFilter()->byId($values['account'])->fetchOne());
			$this->_em->flush();
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $this->_securityUser->getIdentity()->getSelectedAccount()?->getId()]);
		}
	}

	protected function getEntityClass(): ?string
	{
		return null;
	}
}
