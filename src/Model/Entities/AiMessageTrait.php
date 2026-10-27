<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\ConversationInterface;
use ADT\AiChat\Entity\MessageTrait;
use ADT\DoctrineAnonymization\Attributes\Description;
use Doctrine\ORM\Mapping as ORM;

trait AiMessageTrait
{
	// autor, text, data nástrojů, přílohy, tokeny, chyba, created (+ anonymizace) nese balík
	use MessageTrait;

	#[ORM\ManyToOne(targetEntity: 'AiConversation')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	#[Description('Conversation the message belongs to.')]
	protected ConversationInterface $conversation;
}
