<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\ConversationInterface;
use ADT\AiChat\Pseudonymization\PseudonymTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unikátní index (conversation_id, anchor) Doctrine z traitu nepřečte - patří na entitu
 * projektu, viz README (AI asistent - Pseudonymizace vstupu).
 */
trait AiPseudonymTrait
{
	// anchor, value (+ anonymizace) nese balík
	use PseudonymTrait;

	#[ORM\ManyToOne(targetEntity: 'AiConversation')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected ConversationInterface $conversation;
}
