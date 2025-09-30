<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;

trait AccountQueryFactoryInject
{
	protected AccountQueryFactory $_accountQueryFactory;
	public function injectAccountQueryFactory(AccountQueryFactory $accountQueryFactory): void
	{
		$this->_accountQueryFactory = $accountQueryFactory;
	}
}