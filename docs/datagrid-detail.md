# Rozbalovací detail řádku

Grid se `setItemsDetail()` rozbalí po kliku kamkoli do řádku (mimo odkazy a sloupec akcí)
mini-detail pod řádkem. Vzhled i chování dává fancyadmin (`_datagridDetail.scss`,
`datagrid.js`), projekt dodává jen šablonu s obsahem.

- vlevo v prvním sloupci je šipka (`fa-chevron-right`, `--fa-text-faint`), u otevřeného
  řádku otočená dolů v `--primaryColor`; obsah prvního sloupce i detailu začíná až za ní;
- od šipky vede k obsahu linka ve tvaru „L“ (mezistupeň `--fa-border` × `--fa-text-faint`);
- otevřený řádek s detailem je uzavřený blok — linka `--fa-border` nahoře i dole, hover
  podbarví oba řádky;
- otevřený je vždy jen jeden detail v tabulce, klik na jiný řádek předchozí zavře;
- otevření animuje výšku (0,25 s), zavření je okamžité;
- detail je široký jako viditelná část gridu a při vodorovném rolování stojí, roluje jen řádek nad ním;
  stíny lepených sloupců (první sloupec, akce) má jen řádek, detail ne.

Šablona detailu (`$grid->setItemsDetail(__DIR__ . '/XyGridDetail.latte')`):

```latte
<div class="grid-row-detail">
	<div n:if="$item->getAlert()" class="grid-row-detail-alert">
		<i class="fa-regular fa-circle-exclamation"></i>
		<div>{$item->getAlert()}</div>
	</div>

	<div n:if="$item->getNote()" class="grid-row-detail-note">
		<i class="fa-regular fa-note-sticky"></i>
		<div>{$item->getNote()|breaklines}</div>
	</div>

	<div class="grid-row-detail-body">
		<dl class="grid-row-detail-info">
			<div>
				<dt>Adresa</dt>
				<dd>{$item->getAddress() ?? '—'}</dd>
			</div>
		</dl>

		<div class="grid-row-detail-actions">
			<a href="…" class="btn btn-sm btn-outline-secondary"><i class="fa-regular fa-user"></i> Detail</a>
		</div>
	</div>
</div>
```

- `grid-row-detail-alert` — upozornění červeným textem, `grid-row-detail-note` — poznámka
  šedým textem, obojí s ikonou a nejvýš 80 znaků na řádek;
- `grid-row-detail-info` — údaje v mřížce (sloupec min. 170 px), chybějící hodnota „—“;
- `grid-row-detail-actions` — tlačítka `btn-sm btn-outline-secondary` vedle údajů dole,
  na úzké tabulce se zalomí pod ně;
- `grid-row-detail-empty` — šedý text, když detail nemá co ukázat.

Šablona se vykresluje předem pro všechny řádky stránky — nesahat v ní na kolekce
(N+1 dotazů); co je potřeba z dalších tabulek, načte grid naráz pro celou stránku.
