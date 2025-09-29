<?php

namespace ADT\FancyAdmin\DI\Injects;

use Nette\Application\LinkGenerator;

trait LinkGeneratorInject
{
	protected LinkGenerator $_linkGenerator;
	public function injectLinkGenerator(LinkGenerator $linkGenerator): void
	{
		$this->_linkGenerator = $linkGenerator;
	}
}