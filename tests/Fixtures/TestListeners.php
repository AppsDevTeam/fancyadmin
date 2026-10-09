<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Listeners\AccountFieldListenerTrait;
use ADT\FancyAdmin\Model\Listeners\CreatedByListenerTrait;
use ADT\FancyAdmin\Model\Listeners\SelectAccountListenerTrait;

/** Posluchace, jak si je sklada projekt - balicek dodava jen traity. */
final class TestCreatedByListener
{
	use CreatedByListenerTrait;
}

final class TestAccountFieldListener
{
	use AccountFieldListenerTrait;
}

final class TestSelectAccountListener
{
	use SelectAccountListenerTrait;

	/** Trait s touto vlastnosti pracuje, ale nedeklaruje ji - projekt ji musi doplnit. */
	public array $entitiesToRecompute = [];
}
