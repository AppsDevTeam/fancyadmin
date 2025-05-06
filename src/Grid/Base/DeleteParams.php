<?php

namespace App\UI\Portal\Components\Grids\Base;

use Closure;

class DeleteParams
{
	public string $acl;

	public ?Closure $onDelete = null;

	public function __construct(string $acl, ?Closure $onDelete = null)
	{
		$this->acl = $acl;
		$this->onDelete = $onDelete;
	}

	public function getAcl(): string
	{
		return $this->acl;
	}
}
