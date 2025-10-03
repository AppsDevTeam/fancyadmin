<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait QueryObjectDataSourceInject
{
	#[Autowire]
	public IQueryObjectDataSourceFactory $_queryObjectDataSource;
}