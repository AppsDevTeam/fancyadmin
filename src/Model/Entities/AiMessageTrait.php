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

	// plní ChatService (ChatOptions::$senderField); kolega může psát i do cizí konverzace
	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	#[Description('User (identity) who wrote the user message; null for AI replies.')]
	protected ?Identity $sender = null;

	public function getSender(): ?Identity
	{
		return $this->sender;
	}
}
