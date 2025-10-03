<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\Deletable;

use ADT\Datagrid\Component\DeleteParams;

trait Deletable
{
	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams($this->aclResource);
	}
}