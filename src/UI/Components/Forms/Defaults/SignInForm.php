<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormTrait;

class SignInForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInForm
{
	use SignInFormTrait;
}
