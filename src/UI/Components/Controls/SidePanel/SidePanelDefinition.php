<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\SidePanel;

use ADT\Forms\BaseForm;
use Closure;

/** Jeden panel zprava presenteru se `SidePanels`: jak najít záznam a jaký formulář ho upravuje */
final class SidePanelDefinition
{
	/**
	 * @param Closure(?int): object $find záznam podle ID, bez ID nový záznam (neznámé ID je 404)
	 * @param Closure(object): BaseForm $form
	 * @param bool $allowNew nový záznam přes `sidePanelNew!` (měny, země a recenze ho nemají)
	 * @param (Closure(object): string)|null $label nadpis panelu, bez něj getName() záznamu
	 */
	public function __construct(
		private readonly Closure $find,
		private readonly Closure $form,
		public readonly bool $allowNew = true,
		public readonly ?Closure $label = null,
		public readonly SidePanelSize $size = SidePanelSize::Medium,
	) {
	}

	public function find(?int $id): object
	{
		return ($this->find)($id);
	}

	public function createForm(object $entity): BaseForm
	{
		return ($this->form)($entity);
	}
}
