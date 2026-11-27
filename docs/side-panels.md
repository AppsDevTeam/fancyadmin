# Panely zprava na presenteru (SidePanels)

`SidePanelControl` je panel vysouvaný zprava s formulářem. Grid si ho bere přes trait `SidePanel` (jeden panel na
komponentu). Presenter s víc panely nad jedním seznamem (malé formuláře bez vlastní editační stránky) použije
`ADT\FancyAdmin\UI\Presenters\SidePanels`:

```php
use SidePanels;

protected function getSidePanels(): array
{
	return [
		'country' => new SidePanelDefinition(
			find: fn(?int $id) => $this->findOrNew($this->countryQueryFactory->create(), $id, Country::class),
			form: fn(Country $country) => $this->countryFormFactory->create()->setEntity($country),
			allowNew: false,
		),
	];
}

public function createComponentCountrySidePanel(): SidePanelControl
{
	return $this->createSidePanel('country');
}

public function handleEditCountry(int $id): void
{
	$this->openSidePanel('country', $id);
}
```

- Grid úprav vrací v `allowEdit()` `new EditParams('editCountry!')`, tlačítko Nový odkazuje signál `sidePanelNew!`
  s parametrem `panel` (jen panel s `allowNew`).
- Odeslaný formulář nese upravovaný záznam v `editId` (SidePanelControl ho dá do action formuláře). Bez `editId` se
  zakládá nový záznam, proto panel bez `allowNew` odmítne signál i takový POST 404, stejně jako `editId` menší než 1.
- Nadpis panelu je `label` z definice, jinak `getName()` záznamu (`Identifier::__toString()` vrací ID).
- Velikost: `size: SidePanelSize::Large` pro široké formuláře.
- Signály běží na akci seznamu, práva hlídá ona.
