<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\FancyAdmin;

trait FancyAdminInject
{
	private FancyAdmin $_fancyAdmin;
	public function injectFancyAdmin(FancyAdmin $fancyAdmin): void
	{
		$this->_fancyAdmin = $fancyAdmin;
	}
}