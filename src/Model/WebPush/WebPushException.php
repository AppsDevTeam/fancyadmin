<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\WebPush;

/** Push službu prohlížeče se nepodařilo zavolat (síť, odmítnutá zpráva). */
class WebPushException extends \RuntimeException
{
	/** HTTP kód odpovědi push služby, null = odpověď nepřišla */
	public ?int $httpStatus = null;
}
