<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Push;

/** Bez hubu - stránky pollují jako dřív */
final class NullPush implements Push
{
	public function publish(string $topic, array $data = []): void
	{
	}

	public function subscription(array $topics): ?array
	{
		return null;
	}
}
