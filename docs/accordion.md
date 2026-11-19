# Accordion sekcí

Stránka s mnoha formuláři (šablony e-mailů, platební metody) jako karty pod sebou, z nichž je
otevřená jen ta, na které se pracuje. Není to komponenta s vlastním stavem, ale markup, CSS a JS:
sekce jsou obsah stránky, typicky formuláře presenteru.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Accordion/accordion.latte` | blok `accordionSection` pro `{embed}` - titulek, popis, odznak, šipka, tělo v Bootstrap collapse |
| `assets/scss/_accordion.scss` | vzhled (součást `app.scss`), jen `--fa-*` a `--primaryColor`, světlý i tmavý režim |
| `assets/js/accordion.js` | jedna otevřená sekce, sekce s chybou, zapamatování, `?section=`, odznak podle pole |
| `src/lang/fcadmin.*.yml` | výchozí texty odznaku (`fcadmin.accordion.active` / `inactive`) |

JS si projekt importuje sám (`import '@fancyadmin/js/accordion';`), běží na DOM ready i po AJAX překreslení
(`observe()`). Otevírání a zavírání obstarává data API Bootstrapu (`data-bs-toggle="collapse"`);
když projekt nemá `window.bootstrap`, modul při otevření sekce ze skriptu jen přepne třídy.

## Šablona

```latte
{import ADT\FancyAdmin\UI\Components\Accordion\Accordion::TEMPLATE}

<div class="accordion-sections" data-single>
	{foreach $sections as $id => $section}
		{embed accordionSection, id: $id, title: $section->title, description: $section->description, open: $iterator->first}
			{block body}{control $section->control}{/block}
		{/embed}
	{/foreach}
</div>
```

Parametry `accordionSection`:

| parametr | |
|---|---|
| `id` | identifikátor sekce - `data-accordion-section`, `?section=<id>`, klíč zapamatování |
| `title`, `description` | titulek a nepovinný popis v hlavičce |
| `badge` | pevný text odznaku v hlavičce (šedý) |
| `badgeSource` | jméno zaškrtávátka v sekci; odznak pak ukazuje `badgeOn` (zelený) / `badgeOff` (šedý) podle jeho stavu a mění se hned při kliknutí, ne až po uložení |
| `badgeActive` | výchozí stav odznaku s `badgeSource` (hodnota z entity) |
| `badgeOn`, `badgeOff` | texty odznaku, výchozí *Aktivní* / *Neaktivní* z překladů |
| `open` | sekce otevřená ze serveru |

Obsah sekce je blok `body`; v něm platí proměnné stránky (`$control`, `$presenter`).

## Která sekce je otevřená

Při načtení stránky (i po AJAX překreslení) vyhrává v tomto pořadí:

1. **sekce s chybou validace** (`.is-invalid`, `.alert-danger` nebo neprázdný `snippet-…-errors`) - jinak by
   hláška zůstala ve sbalené sekci a nikdo by ji neviděl; chyby z AJAXového odeslání otevřou sekci i dodatečně.
   Jeden formulář přes víc sekcí (jedno *Uložit* pro všechny) zastaví už validace v prohlížeči: po kliknutí na
   odeslání se otevře sekce prvního chybného pole a pole dostane fokus,
2. **`?section=<id>`** v adrese (odkaz na konkrétní sekci; stránka na ni sroluje),
3. **zapamatovaná sekce** - poslední otevřená v `sessionStorage` podle cesty stránky, takže po *Uložit*
   a `redirect('this')` zůstane otevřená ta, ve které se pracovalo, a nevrátí se na první,
4. co otevřel server (`open`).

`data-single` na obalu: nejvýš jedna otevřená sekce (modul nastaví sekcím `data-bs-parent`).
Bez něj se sekce otevírají nezávisle.
