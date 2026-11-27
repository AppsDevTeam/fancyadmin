<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelDefinition;
use Kdyby\Autowired\Attributes\Autowire;

/**
 * Víc panelů zprava (SidePanelControl) na jednom presenteru - úprava malých formulářů místo editační stránky.
 * Presenter popíše panely v getSidePanels(); panel `x` má komponentu `xSidePanel` (createComponentXSidePanel()
 * vrací createSidePanel('x')) a grid úprav volá přes EditParams('editX!') metodu handleEditX($id), která zavolá
 * openSidePanel('x', $id). Nový záznam otevírá signál `sidePanelNew!` s parametrem `panel`. Viz docs/side-panels.md.
 */
trait SidePanels
{
	#[Autowire]
	protected SidePanelControlFactory $sidePanelControlFactory;

	/** @var array<string, object> záznam otevřený signálem, než se panel vykreslí */
	private array $sidePanelEntities = [];

	/** @return array<string, SidePanelDefinition> */
	abstract protected function getSidePanels(): array;

	public function handleSidePanelNew(string $panel): void
	{
		$this->openSidePanel($panel, null);
	}

	protected function openSidePanel(string $panel, ?int $id): never
	{
		$this->sidePanelEntities[$panel] = $this->findSidePanelEntity($this->getSidePanelDefinition($panel), $id);
		$this->redrawSidePanel($panel);
	}

	/** Odeslaný formulář nese upravovaný záznam v `editId` adresy, SidePanelControl ho tam dá při vykreslení */
	protected function createSidePanel(string $panel): SidePanelControl
	{
		$definition = $this->getSidePanelDefinition($panel);
		$editId = $this->getParameter('editId');
		$entity = $editId !== null
			? $this->findSidePanelEntity($definition, (int) $editId)
			: ($this->sidePanelEntities[$panel] ?? $this->findSidePanelEntity($definition, null));

		return $this->sidePanelControlFactory->create()
			->setSize($definition->size)
			->setFormFactory(fn() => $definition->createForm($entity))
			// Identifier::__toString() vrací ID a SidePanelControl ho upřednostní před getName(), nadpis by bylo číslo
			->setResultLabel($definition->label ?? fn(object $entity): string => method_exists($entity, 'getName') ? (string) $entity->getName() : '');
	}

	/**
	 * Záznam podle ID, bez ID nový. Neznámé ID je 404.
	 *
	 * @template T of object
	 * @param object $query query object s byId() a fetchOneOrNull()
	 * @param class-string<T> $class
	 * @return T
	 */
	protected function findOrNew(object $query, ?int $id, string $class): object
	{
		if (!$id) {
			return new $class();
		}

		return $query->byId($id)->fetchOneOrNull() ?? $this->error();
	}

	/** Bez ID nový záznam, jen kde ho panel dovolí - jinak by ho založilo i odeslání formuláře bez `editId` */
	private function findSidePanelEntity(SidePanelDefinition $definition, ?int $id): object
	{
		// editId=0 by jinak prošlo jako nový záznam i u panelu bez allowNew
		if (($id === null && !$definition->allowNew) || ($id !== null && $id < 1)) {
			$this->error();
		}

		return $definition->find($id);
	}

	private function getSidePanelDefinition(string $panel): SidePanelDefinition
	{
		return $this->getSidePanels()[$panel] ?? $this->error();
	}
}
