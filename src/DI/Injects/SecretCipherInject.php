<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\SecretCipher;
use Kdyby\Autowired\Attributes\Autowire;

trait SecretCipherInject
{
	#[Autowire]
	protected SecretCipher $secretCipher;
}
