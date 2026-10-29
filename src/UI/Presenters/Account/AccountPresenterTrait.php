<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Account;

use ADT\FancyAdmin\DI\Injects\AccountFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\UI\Components\Forms\Account\AccountForm;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;

/**
 * Stránka Můj účet - zákazník si upravuje vybraný účet (selectedAccount) formulářem
 * AccountForm, který projekt používá i v backoffice (presenter Accounts). Co zákazník
 * smí měnit, rozhoduje projekt ve formuláři. Přístup řídí ACL (resource `<modul>.account`).
 */
trait AccountPresenterTrait
{
	use PresenterTrait;
	use AccountFormFactoryInject;
	use FancyAdminInject;
	use SecurityUserInject;

	public function actionDefault(): void
	{
		// Single-tenant účty nepoužívá. Akce běží před signály, takže to platí i pro ně.
		if (!$this->_fancyAdmin->isMultiTenant() || !$this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->error();
		}

		$this->getTemplate()->setFile(__DIR__ . '/default.latte');
	}

	public function createComponentAccountForm(): AccountForm
	{
		return $this->_accountFormFactory->create()
			->setEntity($this->_securityUser->getIdentity()->getSelectedAccount())
			->setOnSuccess(function () {
				$this->flashMessageSuccess('fcadmin.sidePanels.control.formSaved');
				$this->redirect('this');
			});
	}
}
