<?php

namespace ADT\FancyAdmin\UI\Forms\LostPassword;

interface LostPasswordFormFactory
{
	public function create(): LostPasswordForm;
}
