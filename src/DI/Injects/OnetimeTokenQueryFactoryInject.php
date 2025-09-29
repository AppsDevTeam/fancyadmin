<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;

trait OnetimeTokenQueryFactoryInject
{
	protected OnetimeTokenQueryFactory $_onetimeTokenQueryFactory;
	public function injectOnetimeTokenQueryFactory(OnetimeTokenQueryFactory $factory): void
	{
		$this->_onetimeTokenQueryFactory = $factory;
	}
}