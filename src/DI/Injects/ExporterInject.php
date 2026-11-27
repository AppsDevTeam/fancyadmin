<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\Exporter\Model\Service\Exporter;
use Kdyby\Autowired\Attributes\Autowire;

trait ExporterInject
{
	#[Autowire]
	protected Exporter $_exporter;
}
