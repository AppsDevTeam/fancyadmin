<?php

namespace App\UI\Portal\Components\Grids\Base;

class EditParams
{
	public string $redirect;

	public function __construct(string $redirect)
	{
		$this->redirect = $redirect;
	}
}
