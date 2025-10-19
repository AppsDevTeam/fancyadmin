<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\Forms\BlockName;

enum BlockNameEnum: string implements BlockName
{
	case ROW = 'row';

	public function getName(): string
	{
		return $this->value;
	}
}
