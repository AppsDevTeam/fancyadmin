<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Enums\GridFilterType;

interface GridFilter extends \ADT\Datagrid\Model\Entities\GridFilter
{
	public function getType(): GridFilterType;
	public function setType(GridFilterType $type): static;

	public function isShared(): bool;
	public function setShared(bool $shared): static;

	/** @return array<string, mixed>|null */
	public function getColumns(): ?array;
	/** @param array<string, mixed>|null $columns */
	public function setColumns(?array $columns): static;

	public function getCreatedBy(): ?Identity;
	public function setCreatedBy(?Identity $createdBy): static;
}
