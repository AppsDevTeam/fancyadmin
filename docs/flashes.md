# Flash zprávy

Plovoucí karty jemně podbarvené barvou typu, dole uprostřed (je-li na stránce rychlá lišta, tak nad ní), skládané do balíčku.

## Typy

| Typ | Metoda presenteru | Barva | Zavírání |
|---|---|---|---|
| `success` | `flashMessageSuccess()` | `--fa-success` | samo po 3 s (`DEFAULT_AUTO_CLOSE_DURATION`) |
| `info` | `flashMessageInfo()` | primární | jen ručně |
| `warning` | `flashMessageWarning()` | `--fa-warning` | jen ručně, tlačítko „Beru na vědomí“ |
| `danger` | `flashMessageError()` | `--fa-danger` | jen ručně, tlačítko „Beru na vědomí“ |

Délku zavření jde u každé zprávy změnit parametrem `$autoCloseDuration` (`null` = jen ručně).
Zprávy s časovačem mají dole proužek se zbývajícím časem.

## Nadpis a text

Zpráva je jeden řetězec, `flashes.js` z něj udělá **nadpis** (první věta) a **text** (zbytek).
„Pacient nemá vyplněný e-mail. Doplňte e-mail a zkuste to znovu.“ = nadpis „Pacient nemá
vyplněný e-mail.“ a text „Doplňte e-mail a zkuste to znovu.“ Hlášku proto
pište jako krátké shrnutí a za ním, co dělat dál. Zpráva s HTML se nedělí.

## Balíček

- Nejnovější je vpředu (nejníž), starší za ní vykukují o 10 px a zmenšují se, od 4. jsou skryté.
- Najetí myší balíček rozestoupí pod sebe a zastaví odpočet všech zpráv.
- Zprávy žijí v `.flash-stack` mimo snippet `body`, takže je ajaxový přechod nezavře. Snippet
  `flashes` nese jen data (`.flash-source`), JS je po načtení stránky i po každém AJAXu převezme.

## Z JS

```js
import { showFlash } from '@fancyadmin/js/flashes';

showFlash('Stav se nepodařilo změnit. Obnovte stránku.', 'danger');
showFlash('Uloženo.', 'success', 3000);
```

## Testy

Zpráva je `.flash-stack .flash-<typ>`, nadpis `.flash-title`, text `.flash-text`. U vícevětých
hlášek jsou nadpis a text dva prvky - `see()` na text přes hranici vět neprojde, hledejte je zvlášť.
