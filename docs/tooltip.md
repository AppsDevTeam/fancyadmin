# Tooltip

Jednotný tooltip celé administrace: bootstrapový `Tooltip` přestylovaný do tiché grafitové bubliny
(podklad `--fa-text`, text `--fa-surface`, bez rámečku). Barvy značky ani stavové barvy nepoužívá, aby
nápověda nepůsobila jako chyba nebo upozornění. V tmavém režimu se díky tokenům obrátí na světlou.
Styly jsou v `assets/scss/_tooltip.scss`, spouštění v `assets/js/tooltip.js` (je v `app.js`, projekt
s vlastním entry pointem přidá `import '@fancyadmin/js/tooltip'`).

## Použití

Stačí atribut `data-bs-toggle="tooltip"` a text v `data-bs-title` (nebo `title`). Nic se neinicializuje,
modul poslouchá na `document`, takže funguje i v prvcích z ajaxových snippetů.

```html
<!-- údaj s tooltipem: kód, zkratka -->
<span tabindex="0" data-bs-toggle="tooltip"
      data-bs-title="<span class='tooltip-title'>211 · ZPMV</span>Zdravotní pojišťovna ministerstva vnitra ČR">211</span>

<!-- ikonové tlačítko -->
<a href="..." class="btn btn-icon" data-bs-toggle="tooltip" data-bs-title="Upravit pacienta"><i class="fa-regular fa-pen"></i></a>

<!-- s klávesovou zkratkou -->
<button data-bs-toggle="tooltip" data-bs-title="Hledat <kbd>Ctrl K</kbd>">...</button>
```

- `data-bs-title` je HTML (`html: true`), prochází sanitizérem Bootstrapu (povolený navíc `kbd`).
  Data z databáze v něm escapujte (`htmlspecialchars`), atribut escapuje Latte nebo `Html::el()`.
- `.tooltip-title` = nadpis (řez 500, plný kontrast), text pod ním je tlumený. U kódů nadpis
  „kód · zkratka“, pod ním celý název. Samotný popisek bez nadpisu má plný kontrast.
- Údaj s tooltipem (kód, zkratka) se vizuálně neoznačuje, dostane jen `tabindex="0"`, aby se k tooltipu
  dostala i klávesnice. Odkazy a tlačítka fokus mají samy.
- Umístění `top`, když nahoře chybí místo, tak `bottom`. Jiné jen u prvků u okraje (boční menu):
  `data-bs-placement="right"` (záložní je vždy opačná strana).
- Text nejvýš na dva řádky, bez tečky na konci. Uvnitř nejsou odkazy ani tlačítka, na to je popover.

## Chování

- Ukáže se po 400 ms najetí myší nebo po fokusu z klávesnice (`:focus-visible`). Při přejetí na sousední
  spouštěč do 300 ms od zavření předchozího se ukáže hned.
- Zmizí hned po odjetí myší, odchodu fokusu, kliknutí kamkoli a po Esc. Esc s otevřeným tooltipem zavře
  jen tooltip, dál k modalu nebo panelu nedojde.
- Na dotyku se neukazuje, proto do něj nepatří nic, bez čeho se uživatel neobejde.
- Objeví se prolnutím 120 ms s posunem 2 px směrem ke spouštěči (`prefers-reduced-motion` bez animace).
  Fade třídu Bootstrapu modul vypíná (`animation: false`): skrytí je okamžité a nový `show()` během
  odjezdu nesmaže čerstvou bublinu.
- Před překreslením snippetu se tooltipy uvnitř zavřou a zruší, bublina nezůstane viset v `body`.

## Vzhled

| Vlastnost | Hodnota |
|---|---|
| Podklad | `--fa-text` (grafitová), bez rámečku |
| Text | popisek `--fa-surface`, popis pod nadpisem `color-mix(in oklch, --fa-surface 78 %, --fa-text)` |
| Stín | `0 8px 28px`, `--fa-shadow-color` na 40 % |
| Zaoblení | `--bs-border-radius-xs` (8 px) |
| Odsazení | 6 × 10 px, max. šířka 280 px, zarovnání vlevo |
| Písmo | 13/18 px, nadpis 500 |
| Šipka | čtverec 8 × 8 px otočený o 45° v barvě podkladu, špička zaoblená 2 px |
| Odstup od spouštěče | 6 px (`offset` Bootstrapu) |
| `kbd` | 12 px, podklad 16 % `--fa-surface` do `--fa-text`, text `--fa-surface`, zaoblení `--bs-border-radius-xxs` |

Hodnoty jsou v CSS proměnných `--bs-tooltip-*` na `.tooltip`, projekt je nepřebíjí.
