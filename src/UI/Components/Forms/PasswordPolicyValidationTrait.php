<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\PasswordPolicy;
use ADT\Forms\Form;

/**
 * Spolecna validace hesla pro formulare, ktere ho nastavuji - NewPassword i ChangePassword.
 */
trait PasswordPolicyValidationTrait
{
	protected function validatePasswordPolicy(string $password, Form $form): void
	{
		/** @var Identity $identity */
		$identity = $this->getEntity();
		$field = $form->getComponentTextInput('password');

		$violations = PasswordPolicy::violationsFor($password, $identity->getEmail(), $identity->getUsername(), $identity->getRoles());

		foreach (PasswordPolicy::translateViolations($this->getTranslator(), $violations) as $_message) {
			$field->addError($_message, false);
		}
	}
}
