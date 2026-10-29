# Záložky, přepínač pohledu a filtr

Tři prvky, tři otázky. Každý má vlastní tvar, aby uživatel poznal, jestli mění sekci,
zobrazení, nebo jen filtruje.

| prvek | otázka | kdy |
|---|---|---|
| `.nav-tabs` | Kam jdu? | pod záložkami se mění celý obsah (sekce karty, nastavení) |
| `.nav-tabs.nav-tabs-sm` | Kam jdu uvnitř panelu? | 2. úroveň, mění se struktura obsahu panelu |
| `.view-switch` | Jak to zobrazím? | stejná data, jiné zobrazení (Den/Týden, Kalendář/Tabulka) |
| `.select-filter` | Co z toho vidím? | podmnožina téhož seznamu |

Pravidla: záložky jen jedna úroveň na stránce a nikdy na dva řádky; přepínač pohledu
2–4 položky a je to jediný plný (primární) přepínač na stránce; filtr je select, ne
řada tlačítek ani chipy. `btn-group` zůstává jen pro skutečné akce.

---

## Co je kde

| soubor | co dělá |
|---|---|
| `assets/scss/_tabs.scss` | podtržené záložky přes proměnné Bootstrapu `--bs-nav-*`, jezdec, posuvná lišta, menu „Další“ |
| `assets/js/tabs.js` | jezdec pod aktivní záložkou, přetečení do „Další“ (Bootstrap dropdown), pod 992 px posuvná lišta se šipkami |
| `assets/scss/_viewSwitch.scss` | přepínač = `.btn-group` s plnou aktivní položkou (jen CSS) |
| `assets/scss/_selectFilter.scss` | filtr s popiskem, otevřený seznam `.select-filter-dropdown` |
| `assets/js/selectFilter.js` | šířka filtru podle nejdelší volby |

SCSS je součástí `app.scss`. JS si projekt importuje sám (`@fancyadmin/js/tabs`, `@fancyadmin/js/selectFilter`);
moduly se spouští na DOM ready i po AJAX překreslení.

Barvy: linka a text z `--fa-border`, `--fa-text-muted`, `--fa-heading`, podtržení
a aktivní položka přepínače z `--primaryColor` a `--onPrimaryColor`. Projekt nic nepřebíjí.

---

## Záložky

Běžný Bootstrap markup, nic navíc:

```html
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#a">Obecné</button></li>
  <li class="nav-item"><a class="nav-link" href="…">Sklad <span class="badge bg-warning">1</span></a></li>
</ul>
```

`tabs.js` záložky obalí do `.nav-tabs-wrap` (linka je na obalu, aby šla i pod šipky)
a utility třídy okrajů (`mb-3`, `mt-2`…) přesune z `ul` na obal.

- **≥ 992 px:** co se nevejde, spadne zprava do „Další“. Je-li
  aktivní sekce schovaná, „Další“ převezme její název. Menu je Bootstrap dropdown laděný jako rozbalený Select2.
- **< 992 px** a podzáložky `.nav-tabs-sm`, které se nevejdou: posuvná lišta se šipkami;
  aktivní záložka se vycentruje.
- V lištách, které se vejdou, šipky ani „Další“ nejsou.

## Přepínač pohledu

```html
<div class="btn-group btn-group-sm view-switch" role="group" aria-label="Zobrazení">
  <a class="btn btn-outline-secondary active" href="…" aria-current="page">Den</a>
  <a class="btn btn-outline-secondary" href="…">Týden</a>
</div>
```

Spojená obrysová tlačítka; velikost, písmo a odsazení jsou tlačítkové (`.btn-group-sm`
= `btn-sm`), `.view-switch` jen plní aktivní položku primární barvou. Bez JS: položky
jsou odkazy (`aria-current`) nebo tlačítka (`aria-pressed` si řeší projekt). Položka
s ikonou má popisek ve `<span>`; pod 992 px zůstane jen ikona, proto `title`.

## Filtr

```html
<form class="select-filter">
  <label class="form-label" for="f-cat">Kategorie</label>
  <select id="f-cat" class="form-select form-select-sm"
    data-adt-select2='{"dropdownAutoWidth": true, "dropdownCssClass": "select-filter-dropdown"}'>
    <option value="">Vše</option>
    …
  </select>
</form>
```

Popisek je vždy vidět. Šířka podle nejdelší volby (strop 240 px), takže se při
přepínání nemění; otevřený seznam smí být širší (max 320 px). `form` je tam kvůli
Select2 z adt-js-components, který při změně volá `Nette.toggleForm(closest('form'))`.
