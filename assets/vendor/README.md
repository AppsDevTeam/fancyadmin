# Přibalené knihovny třetích stran

Stránky tabletu (čekací stránka, podpis, `docs/tablets.md`) jsou samostatné - nezávisí na
bundlu ani stylech projektu, aby vypadaly a fungovaly ve všech projektech stejně. Proto jsou
knihovny, které potřebují, přibalené tady jako hotové ES moduly a servíruje je presenter tabletu
(`TabletDevicePresenterTrait::actionAsset`).

| Složka | Knihovna | Verze | Licence | Zdroj |
|---|---|---|---|---|
| `signature_pad/` | signature_pad | 5.1.4 | MIT | npm `signature_pad`, `dist/signature_pad.min.js` |
| `pdfjs/` | pdfjs-dist (legacy build) | 6.5.438 | Apache-2.0 | npm `pdfjs-dist`, `legacy/build/pdf.min.mjs`, `pdf.worker.min.mjs` |

Aktualizace: `npm install signature_pad pdfjs-dist` v dočasné složce a zkopírovat uvedené soubory
(u pdf.js starší sestavení `legacy/build` - běží i na starších tabletech) včetně `LICENSE`.
`signature_pad` musí odpovídat verzi, se kterou počítá `adt/biometric-signature`.
