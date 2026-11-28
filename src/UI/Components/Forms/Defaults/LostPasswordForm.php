<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormTrait;

class LostPasswordForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordForm
{
	use LostPasswordFormTrait;
}
