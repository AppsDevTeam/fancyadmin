# Tisk stránky

`assets/scss/_print.scss` (součást `app.scss`) upraví pro tisk každou stránku administrace:

- Obsah (`.container-main`) má na obrazovce `position: fixed` a vlastní posuvník. Pro tisk se vrací do toku stránky, jinak by se vytiskl jen viditelný výřez.
- Boční menu, quickbar, flash zprávy, side panely, modaly, šipka zpět, `.header-actions` a všechna tlačítka (`.btn`) se netisknou.
- Tisk je vždy světlý: bílý papír a černý text i v tmavém režimu. Karty a buňky tabulek jsou bez pozadí a karta se nedělí mezi stránky. `.badge` si nechává svou barvu.

Co se má z konkrétní stránky vynechat (ovládací formuláře, historie), označí projekt třídou `d-print-none`. Co se má tisknout jen na papír (třeba stav místo selectu), dostane `d-none d-print-inline` nebo `d-none d-print-block`. Obojí jsou utility Bootstrapu.

Tlačítko pro tisk potřebuje jen `window.print()` z JS komponenty. Odkaz `javascript:window.print()` je inline skript, který CSP s nonce nepustí.
