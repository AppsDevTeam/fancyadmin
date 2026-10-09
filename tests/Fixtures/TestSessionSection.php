<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Nette\Http\SessionSection;

final class TestSessionSection extends SessionSection
{
	/** @var array<string, ?string> klic => expirace, jak ji volajici predal */
	public array $expirations = [];

	public function __construct(private readonly TestSession $testSession, private readonly string $sectionName)
	{
		parent::__construct($testSession, $sectionName);
		$this->testSession->data[$sectionName] ??= [];
	}

	public function set(string $name, mixed $value, ?string $expire = null): void
	{
		if ($value === null) {
			$this->remove($name);
			return;
		}

		$this->testSession->data[$this->sectionName][$name] = $value;
		$this->expirations[$name] = $expire;
	}

	public function get(string $name): mixed
	{
		return $this->testSession->data[$this->sectionName][$name] ?? null;
	}

	public function remove(string|array|null $name = null): void
	{
		if ($name === null) {
			$this->testSession->data[$this->sectionName] = [];
			return;
		}

		foreach ((array) $name as $_name) {
			unset($this->testSession->data[$this->sectionName][$_name], $this->expirations[$_name]);
		}
	}

	public function getIterator(): \Iterator
	{
		return new \ArrayIterator($this->testSession->data[$this->sectionName]);
	}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->testSession->data[$this->sectionName][$offset]);
	}

	public function offsetGet(mixed $offset): mixed
	{
		return $this->get((string) $offset);
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		$this->set((string) $offset, $value);
	}

	public function offsetUnset(mixed $offset): void
	{
		$this->remove((string) $offset);
	}
}
