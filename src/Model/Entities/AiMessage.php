<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\MessageInterface;

/**
 * Jedna zpráva konverzace s AI asistentem - dotaz uživatele nebo odpověď AI.
 * Entity nedědí ze stejného důvodu jako {@see AiConversation}.
 */
interface AiMessage extends MessageInterface
{
}
