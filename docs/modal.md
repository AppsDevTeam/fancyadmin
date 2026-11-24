# Modal

Modal ve vzhledu fancyadminu: hlavička s ikonou, titulkem a křížkem, tělo s poli pod sebou a patička se „Zavřít“
a hlavním tlačítkem. Není to komponenta se stavem, jen šablona pro `{embed}` a styly; otevírání a zavírání
obstarává Bootstrap (`data-bs-toggle="modal"`). Vyjíždějící boční panel (`SidePanel`) má vlastní vzhled.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Modal/modal.latte` | blok `modal` pro `{embed}`, blok `modalClose` (tlačítko Zavřít do patičky) |
| `assets/scss/_modal.scss` | užší dialog `.modal-dialog-narrow` (600 px), hlavní tlačítko formuláře v modalu min. 240 px (formulář z rendereru ho má na střed), flex formuláře ve scrollovatelném modalu, z-index backdropu |
| `src/lang/fcadmin.*.yml` | `fcadmin.modal.close` |
| `assets/js/lazyModal.js` | po zobrazení dá kurzor do prvního pole (`focusFirstField()`, u líného modalu po načtení obsahu) a TAB drží uvnitř modalu (`keepTabInside()`), README - Fokus na první pole |

## Šablona

```latte
{import ADT\FancyAdmin\UI\Components\Modal\Modal::TEMPLATE}

{embed modal, id: 'smsModal', title: 'Odeslat SMS pacientovi', icon: 'comment-sms', form: [method: post, action: $sendUrl, class: ajax]}
	{block body}
		<div class="mb-3">
			<label class="form-label" for="phone">Telefonní číslo</label>
			<input type="text" name="phone" id="phone" class="form-control">
		</div>
	{/block}
	{block footer}
		{include modalClose}
		<button type="submit" class="btn btn-primary"><i class="fa-regular fa-paper-plane"></i> Odeslat SMS</button>
	{/block}
{/embed}
```

Parametry `modal`:

| parametr | |
|---|---|
| `id`, `title` | id modalu (titulek dostane `{id}Title` pro `aria-labelledby`) a titulek |
| `icon` | ikona v titulku - jméno Font Awesome (`comment-sms` → `fa-regular fa-comment-sms`) nebo plné třídy |
| `size` | `narrow` (výchozí, 600 px), `sm`, `lg`, `xl`, `''` = šířka Bootstrapu |
| `scrollable`, `centered` | `modal-dialog-scrollable` (výchozí zapnuto), `modal-dialog-centered` |
| `form` | atributy `<form>`, který obalí hlavičku, tělo i patičku - tlačítko v patičce pak formulář odešle |
| `lazyUrl`, `lazyLoaded` | `data-lazy-url` a `data-lazy-state="loaded"` pro obsah načítaný až při otevření (`docs/lazy-modal.md`) |
| `show` | `data-adt-show-modal` - modal se otevře hned po načtení stránky (formulář s chybou) |

Blok `body` je tělo, blok `footer` patička - bez obsahu se patička nevykreslí. Formulář vykreslený rendererem
(`{control form}`) má odeslání v těle; styly ho dají na střed a roztáhnou na 240 px, patička pak není potřeba. Na telefonu (pod `sm`) si 240 px
drží jen odeslání v těle; v patičce se vedle tlačítka Zavřít dělí o šířku rovným dílem.
