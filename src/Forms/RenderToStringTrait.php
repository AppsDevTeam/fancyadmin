<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Form;

trait RenderToStringTrait
{
	public function renderToString(): false|string
	{
		ob_start();
		$this->render();
		return ob_get_clean();
	}
}
