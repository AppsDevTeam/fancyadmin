<?php

namespace ADT\FancyAdmin\DI\Injects;

use Doctrine\ORM\EntityManagerInterface;

trait EntityManagerInject
{
	protected EntityManagerInterface $_em;
	public function injectEntityManager(EntityManagerInterface $em): void
	{
		$this->_em = $em;
	}
}