<?php

namespace ADT\FancyAdmin\Model\Queries;

trait AclRoleQueryTrait
{
	public function byIsAdmin(bool $isAdmin): static
	{
		return $this->by('isAdmin', $isAdmin);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}