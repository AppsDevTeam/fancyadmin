<?php

namespace ADT\FancyAdmin\UI;

trait RenderToStringTrait
{
	public function renderToString(): false|string
	{
		ob_start();
		$this->render();
		return ob_get_clean();
	}
}
