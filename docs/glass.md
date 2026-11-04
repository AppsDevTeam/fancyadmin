# Sklo

Poloprůhledné plovoucí vrstvy (rychlá lišta a její panely, boční panel) mají společné sklo z `assets/scss/_glass.scss`.

| Co | Kde |
|---|---|
| `@include glass($alpha, $border, $highlight)` | plocha, rozostření, rámeček, vrchní hrana a stín. Panely 70 %, lišta 53 % |
| `@include glass-inputs` | pole na skle mají podklad jako sklo panelu (`--glass-input-background`, 70 %), rámečky beze změny |
| `@include thin-scrollbar` | tenký posuvník posuvných částí na skle |
| `.btn.btn-glass` | ikonové tlačítko na skle (`btn btn-icon btn-sm btn-glass`), aktivní stav je `btn-primary` |

Tokeny jsou na `body`, projekt je může přemapovat:

- `--glass-filter`: `blur(40px) saturate(180%)`
- `--glass-shadow`: stín plovoucí vrstvy
- `--glass-input-background`: podklad polí na skle
- `--fa-line`: jemné linky pod hlavičkou a nad patičkou (slabší než `--fa-border`)

Jinde jsou pole bílá (`--inputBackground` projektu), sklo je jediná výjimka.
