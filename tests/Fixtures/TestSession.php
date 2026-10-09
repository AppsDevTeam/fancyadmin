<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Nette\Http\Session;
use Nette\Http\SessionSection;

/**
 * Session drzena v poli - bez PHP session handleru, bez hlavicek, bez souboru na disku.
 * Testy diky tomu bezi v CLI a muzou se podivat primo na to, co se do sekce ulozilo.
 */
final class TestSession extends Session
{
	/** @var array<string, array<string, mixed>> */
	public array $data = [];

	/** @var array<string, TestSessionSection> */
	private array $sections = [];

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct()
	{
	}

	public function getSection(string $section, string $class = SessionSection::class): SessionSection
	{
		return $this->sections[$section] ??= new TestSessionSection($this, $section);
	}

	public function hasSection(string $section): bool
	{
		return !empty($this->data[$section]);
	}

	public function getSectionNames(): array
	{
		return array_keys($this->data);
	}
}
