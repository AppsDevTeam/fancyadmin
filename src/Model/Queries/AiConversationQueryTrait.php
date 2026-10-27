<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;

trait AiConversationQueryTrait
{
	public function byIdentity(int|Identity $identity): static
	{
		return $this->by('identity', $identity);
	}

	/** Smazání konverzace je jen příznak (ChatService::deleteConversation), řádek zůstává. */
	public function notDeleted(): static
	{
		return $this->by('deleted', false);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('updated', 'DESC');
	}
}
