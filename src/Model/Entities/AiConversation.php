<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\ConversationInterface;

/**
 * Konverzace s AI asistentem (adt/ai-chat), patří jedné identitě.
 *
 * Rozhraní nedědí Entity - jeho getId() se s getId() z ConversationInterface
 * neslučuje. Entitu Entity dostane od BaseEntity projektu, viz README (sekce 28).
 */
interface AiConversation extends ConversationInterface
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;
}
