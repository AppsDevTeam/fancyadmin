# Sklo

Poloprůhledné plovoucí vrstvy (rychlá lišta a její panely, boční panel) mají společné sklo z `assets/scss/_glass.scss`.

| Co | Kde |
|---|---|
| `@include glass($alpha, $border, $highlight, $dark-alpha)` | plocha, rozostření, rámeček, vrchní hrana a stín. Panely 70 %, lišta 53 % (ve tmavém 84 %) |
| `@include glass-inputs` | pole na skle mají podklad jako sklo panelu (`--glass-input-background`, 70 %), rámečky beze změny |
| `@include thin-scrollbar` | tenký posuvník posuvných částí na skle |
| `.btn.btn-glass` | ikonové tlačítko na skle (`btn btn-icon btn-sm btn-glass`), aktivní stav je `btn-primary` |

Tokeny jsou na `body`, projekt je může přemapovat:

- `--glass-filter`: `blur(40px) saturate(180%)`
- `--glass-shadow`: stín plovoucí vrstvy
- `--glass-input-background`: podklad polí na skle
- `--fa-line`: jemný oddělovač, slabší než `--fa-border` (rámeček bubliny chatu)

Linky pod hlavičkou a nad patičkou panelů jsou `--fa-border`.

Jinde jsou pole bílá (`--inputBackground` projektu), sklo je jediná výjimka.

Ve tmavém režimu by tmavé sklo na tmavé stránce zmizelo, proto je „zvýrazněné“: plocha ze zesvětleného povrchu (`--glass-base`), rámeček a hrana bílé (šestina světlého krytí), tmavý stín s lemem, pole a `btn-glass` s bílým nádechem 6 %. Lupa a „Hledat“ v liště jsou napůl mezi textem a tlumenou barvou, aby byly čitelné i nad světlým obsahem.

## Boční panel

Plovoucí sklo 12 px od okrajů okna, šířka dál podle `data-size`. Hlavička `.side-panel-template-header` (nadpis `.side-panel-template-title`, křížek `.side-panel-template-close`) i spodní lišta `.side-panel-actions` stojí, posouvá se jen tělo: snippet, a u formuláře s lištou obal polí `.side-panel-scroll`, který skládá `sidePanel.js`. Vrstvený panel má sklo 80 %, panel pod ním se posune o 48 px a ztmaví. Linka pod hlavičkou se objeví až po odrolování a nad lištou je jen, dokud je pod ní další obsah (třídy `--scrolled` a `--more` na kontejneru z `sidePanel.js`).

Nadpis hlavičky: `SidePanelControl::setTitle()`, jinak u úpravy název záznamu (`setResultLabel()`, `__toString()`, `getFullName()`, `getName()`, `getTitle()`) a u nového záznamu text tlačítka, které panel otevřelo (ikonové tlačítko dá svůj `title`). Záložky `.nav-tabs` na začátku formuláře se přesunou pod nadpis (`.side-panel-template-tabs`, linka pod nimi vždy), přepnutí záložky posune tělo nahoru a chyba validace v jiné záložce na ni přepne.

Hlavička záznamu osoby: `setAvatar()` (iniciály v kolečku vlevo, nadpis je pak 17 px) a `setSubtitle()` (řádek pod nadpisem, např. „Muž, 36 let“).

## Přehled záznamu v panelu

Panel bez formuláře (`setBodyTemplate()`) skládá obsah z boxů na skle:

| Třída | Co |
|---|---|
| `.side-panel-box` | plocha na skle (mixin `glass-box`), zaoblení `sm` |
| `.side-panel-box--dashed` | prázdný stav, jen čárkovaný obrys |
| `.side-panel-section` | sekce: `.side-panel-section-header` (nadpis `.side-panel-section-title` verzálkami, vpravo odkaz) a pod ní box |
| `.side-panel-info` | `dl` v boxu, řádky `div > dt + dd` oddělené linkou; prázdná hodnota `.side-panel-info-empty` |

Akce jsou `btn btn-sm btn-glass`. Uvnitř skla jsou linky `--glass-line` a najetí `--glass-hover`. Prvky v boxu u jeho okraje mají zaoblení soustředné (box `sm` − odsazení 4 px = `xs`).
