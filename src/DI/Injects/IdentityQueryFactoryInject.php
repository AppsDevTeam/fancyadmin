<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;

trait IdentityQueryFactoryInject
{
	private IdentityQueryFactory $_identityQueryFactory;
	public function injectIdentityQueryFactory(IdentityQueryFactory $factory): void
	{
		$this->_identityQueryFactory = $factory;
	}
}