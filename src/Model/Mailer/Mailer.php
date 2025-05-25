<?php

namespace ADT\FancyAdmin\Model\Mailer;

use ADT\FancyAdmin\Model\Entities\Identity;

interface Mailer
{
	public function sendAccountCreationEmail(Identity $identity): void;
	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime): void;
}
