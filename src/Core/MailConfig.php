<?php

namespace ADT\FancyAdmin\Core;

class MailConfig
{
	public function __construct(private ?string $logoFileName = null)
	{
	}

	public function getLogoFileName(): ?string
	{
		return $this->logoFileName;
	}
}