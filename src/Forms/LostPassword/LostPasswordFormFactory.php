<?php

namespace ADT\FancyAdmin\Forms\LostPassword;

interface LostPasswordFormFactory
{
	public function create(): LostPasswordForm;
}
