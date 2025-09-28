<?php

namespace ADT\FancyAdmin\DI\Injects;

use Doctrine\ORM\EntityManagerInterface;

trait EntityManagerInject
{
	private EntityManagerInterface $_em;
	public function injectEntityManager(EntityManagerInterface $em): void
	{
		$this->_em = $em;
	}
}