<?php

namespace ADT\FancyAdmin\UI\Forms\LostPassword;

use ADT\FancyAdmin\UI\Forms\LostPassword\LostPasswordForm;

interface LostPasswordFormFactory
{
	public function create(): LostPasswordForm;
}
