<?php

namespace ADT\FancyAdmin\DI\Injects;

use Nette\Application\LinkGenerator;

trait LinkGeneratorInject
{
	private LinkGenerator $_linkGenerator;
	public function injectLinkGenerator(LinkGenerator $linkGenerator): void
	{
		$this->_linkGenerator = $linkGenerator;
	}
}