<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorFormTrait;

class TwoFactorForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorForm
{
	use TwoFactorFormTrait;
}
