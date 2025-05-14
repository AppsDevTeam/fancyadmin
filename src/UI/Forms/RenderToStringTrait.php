<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Forms;

trait RenderToStringTrait
{
	public function renderToString(): false|string
	{
		ob_start();
		$this->render();
		return ob_get_clean();
	}
}
