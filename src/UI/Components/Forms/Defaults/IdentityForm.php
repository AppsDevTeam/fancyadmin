<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\Identity\IdentityFormTrait;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\Forms\Form;
use Nette\Forms\Container;

class IdentityForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\Identity\IdentityForm
{
	use IdentityFormTrait;

	/** @param array<mixed> $roles */
	protected function addProfileFields(Form|Container $form, ?Profile $profile, array $roles): void
	{
	}

	/** @param array<mixed> $roles */
	protected function addIdentityFields(Form|Container $form, ?Identity $identity, array $roles): void
	{
	}
}
