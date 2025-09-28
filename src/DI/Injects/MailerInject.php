<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Mailer\Mailer;

trait MailerInject
{
	private Mailer $_mailer;
	public function injectMailer(Mailer $mailer): void
	{
		$this->_mailer = $mailer;
	}
}