<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait QueryObjectDataSourceInject
{
	#[Autowire]
	protected IQueryObjectDataSourceFactory $_queryObjectDataSource;
}