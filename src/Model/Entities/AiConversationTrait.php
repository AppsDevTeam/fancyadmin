<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\ConversationTrait;
use ADT\DoctrineAnonymization\Attributes\Description;
use Doctrine\ORM\Mapping as ORM;

trait AiConversationTrait
{
	// název, id session, příznak smazání, created, updated (+ anonymizace) nese balík
	use ConversationTrait;

	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	#[Description('User (identity) who owns the conversation.')]
	protected Identity $identity;

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}
}
