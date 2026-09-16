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

		if (PasswordPolicy::matchesIdentifier($password, $identity->getEmail(), $identity->getUsername())) {
			$field->addError('fcadmin.forms.newPassword.errors.sameAsIdentifier');
		}

		if (!$policy = PasswordPolicy::strictestOf($identity->getRoles())) {
			return;
		}

		foreach ($policy->violations($password) as $_violation) {
			$field->addError($this->getTranslator()->translate(...$_violation));
		}
	}
}
