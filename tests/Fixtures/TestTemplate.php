<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Nette\Application\UI\Template;

/** Sablona jen jako nosic promennych - Latte engine testy nepotrebuji. */
#[\AllowDynamicProperties]
final class TestTemplate implements Template
{
	private string $file = '';

	public function render(?string $file = null, array $params = []): void
	{
	}

	public function setFile(string $file): static
	{
		$this->file = $file;
		return $this;
	}

	public function getFile(): ?string
	{
		return $this->file;
	}
}
