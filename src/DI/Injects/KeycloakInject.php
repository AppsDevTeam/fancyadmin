<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use Kdyby\Autowired\Attributes\Autowire;

trait KeycloakInject
{
	#[Autowire]
	protected Keycloak $_keycloak;
}
