<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\Account\AccountFormTrait;
use ADT\FancyAdmin\Model\Entities\Account;

class AccountForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\Account\AccountForm
{
	use AccountFormTrait;

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Account::class);
	}
}
