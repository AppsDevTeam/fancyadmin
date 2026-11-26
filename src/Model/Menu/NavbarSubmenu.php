<?php

namespace ADT\FancyAdmin\Model\Menu;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Application\UI\Component;

class NavbarSubmenu
{
	protected ?string $title = null;

	/** @var array<NavbarSubmenuItem|NavbarSubmenuHeading> */
	protected array $subMenuItems = [];

	public function __construct(
		protected NavbarMenuItem $parent
	) {}

	public function addMenuItem(NavbarSubmenuItem $subMenuItems): self
	{
		$this->subMenuItems[] = $subMenuItems;
		return $this;
	}

	public function addHeading(string $label): self
	{
		$this->subMenuItems[] = new NavbarSubmenuHeading($label);
		return $this;
	}

	/**
	 * @return array<NavbarSubmenuItem|NavbarSubmenuHeading>
	 */
	public function getSubMenuItems(): array
	{
		return $this->subMenuItems;
	}

	private bool $hideEmptyHeadings = false;

	/**
	 * Skrýt nadpis, pod kterým (do dalšího nadpisu) uživatel nevidí žádnou položku - v seskupeném podmenu by
	 * jinak zůstal nadpis bez položek. Výchozí je jako dřív: nadpisy se zobrazují vždy.
	 */
	public function setHideEmptyHeadings(bool $hide = true): static
	{
		$this->hideEmptyHeadings = $hide;
		return $this;
	}

	/**
	 * Položky, které uživatel vidí, a nadpisy (s setHideEmptyHeadings() jen ty, pod kterými je viditelná položka).
	 *
	 * @return array<NavbarSubmenuItem|NavbarSubmenuHeading>
	 */
	public function getVisibleSubMenuItems(SecurityUser $user, ?Component $presenter = null): array
	{
		$visible = [];
		$heading = null;
		foreach ($this->subMenuItems as $item) {
			if ($item instanceof NavbarSubmenuHeading) {
				if (!$this->hideEmptyHeadings) {
					$visible[] = $item;
					continue;
				}
				$heading = $item;
				continue;
			}
			if (!$item->isVisible($user, $presenter)) {
				continue;
			}
			if ($heading !== null) {
				$visible[] = $heading;
				$heading = null;
			}
			$visible[] = $item;
		}
		return $visible;
	}

	public function getTitle(): ?string
	{
		return $this->title ?? $this->parent->getLabel();
	}

	public function setTitle(?string $title): self
	{
		$this->title = $title;
		return $this;
	}

}