<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyFormTrait;

class ApiKeyForm extends DefaultBaseForm implements \ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyForm
{
	use ApiKeyFormTrait;
}
