# Biometrický podpis

Podpis prstem nebo perem na dotykovém zařízení se záznamem průběhu psaní (souřadnice v čase,
tlak, náklon pera), potvrzený **kódem z SMS** nebo **personálem na místě** (tablet provozovatele,
viz `docs/tablets.md`). Po ověření vznikne důkazní záznam, jehož otisk SHA-256 se tiskne do PDF.

Logika je v knihovně [adt/biometric-signature](https://github.com/AppsDevTeam/biometric-signature)
(entita, kódy a limity, důkazní záznam, JS plátna) - její README je hlavní dokumentace.
Fancyadmin k ní dodává zapojení do Nette:

| Část | Co dělá |
|---|---|
| `fancyadmin: biometricSignature` | služby knihovny (`BiometricSigning`, `EvidenceVerifier`, `SigningPolicy` z konfigurace) |
| `$form->addBiometricSignature()` | pole formuláře (`BiometricSignatureInput`), hodnota je `?SignatureCapture` |
| `@fancyadmin/js/biometricSignature` | napojení plátna při načtení stránky a po AJAX překreslení |
| `@fancyadmin/scss/biometric-signature` | vzhled plátna |

Projekt dál dodává entitu podpisu, `SigningHandler` (odeslání SMS, obsah důkazního záznamu, PDF)
a stránky (formulář s podpisem, zadání kódu).

## Instalace

Knihovna je volitelná závislost fancyadminu - projekty bez podpisu ji nepotřebují:

```bash
composer require adt/biometric-signature
yarn add signature_pad
```

Dokud knihovna není na Packagistu, patří do `repositories` projektu:

```json
{ "type": "git", "url": "https://github.com/AppsDevTeam/biometric-signature.git" }
```

a do `require` `"adt/biometric-signature": "dev-main"`.

## Konfigurace

```neon
fancyadmin:
	biometricSignature:
		enabled: true
		policy:                     # nepovinné, null = výchozí hodnota knihovny
			codeValidMinutes: 15
			linkValidMinutes: 60

services:
	- App\Model\Signing\FormSigningHandler   # implements ADT\BiometricSignature\SigningHandler
```

`enabled: true` bez nainstalované knihovny shodí kompilaci kontejneru s návodem.
`BiometricSigning` si `SigningHandler` i `EntityManagerInterface` autowiruje; hodiny jsou PSR-20
`ClockInterface`, bez služby systémové.

## Formulář

```php
$form->addBiometricSignature('signature', 'Podpis')
	->setRequired('Podepište se prosím.');
	// ->setClearCaption('Clear signature')  - jiný popisek tlačítka (výchozí přeložený)
	// ->setInvalidMessage('The signature could not be captured…')  - jiná hláška neplatného podpisu

// processForm
/** @var SignatureCapture|null $capture */
$capture = $form['signature']->getValue();
if ($capture !== null) {
	$signature = new FormSignature($submission, $capture, $phone);
	$signature->setOnSite($onTablet);
	$signature->setRequestContext($httpRequest->getRemoteAddress(), $httpRequest->getHeader('User-Agent'));
	$this->entityManager->persist($signature);
	$this->entityManager->flush();

	$onTablet
		? $this->biometricSigning->completeOnSite($signature)
		: $this->biometricSigning->sendCode($signature);
}
```

Pole vykreslí plátno, tlačítko „Vymazat podpis“ a dva hidden inputy (`<name>[image]`,
`<name>[biometrics]`). Hodnoty se nevracejí do HTML: plátno se po překreslení vždy smaže, takže
po chybě v jiném poli se musí podepsat znovu.

- Hodnota je `SignatureCapture` (obrázek i biometrika zkontrolované) nebo `null` (nepodepsáno).
- Podvržený nebo poškozený obsah inputů je **chyba pole** („Podpis se nepodařilo zachytit…“),
  ne prázdná hodnota - jinak by u nepovinného podpisu prošel jako „bez podpisu“.
- Povinnost (`setRequired()`) hlídá i klientská validace nette-forms (na inputu s obrázkem).

## Prohlížeč

JS knihovny se bere přímo z `vendor/` (jedna verze s PHP). Projekt potřebuje alias ve Vite:

```js
// vite.config.mjs
alias: {
	'@fancyadmin': path.resolve(__dirname, 'vendor/adt/fancyadmin/assets'),
	'@biometric-signature': path.resolve(__dirname, 'vendor/adt/biometric-signature/assets'),
},
```

Modul není v `app.js` fancyadminu - projekt bez podpisu by alias neměl a build by spadl. Načíst ho
jen na stránce s podpisem:

```js
if (document.querySelector('[data-biometric-signature]')) {
	import('@fancyadmin/js/biometricSignature').then(({ initBiometricSignaturePage }) => initBiometricSignaturePage());
}
```

`initBiometricSignaturePage()` napojí podpisy hned a po každém AJAX požadavku (nette.ajax, je-li
na stránce); už napojené přeskočí. Pro vlastní kontejner je `initBiometricSignatureFields(root)`.

```scss
@use '@fancyadmin/scss/biometric-signature';

:root {
	--signature-pen-color: var(--app-primary);   // výchozí --bs-primary
	--signature-height: 220px;                    // výchozí 200px
	--signature-background: var(--app-surface-sunken);
	--signature-border-color: var(--app-border);
}
```

Plátno musí mít výšku z CSS - skript podle rozměrů nastaví jeho rozlišení. Změna rozměrů podpis
smaže (biometrika by neseděla s obrázkem), skryté plátno (modal, záložka) se napojí, až se zobrazí.

## Ověření kódem a důkazní záznam

Průběh (`sendCode()`, `verifyCode()`, `completeOnSite()`), výsledky (`SendCodeResult`,
`VerifyCodeResult`), výchozí limity a ověření podpisu proti PDF (`EvidenceVerifier`) popisuje
README knihovny. Stránku se zadáním kódu dělá projekt - pole kódu s
`autocomplete="one-time-code"` a `inputmode="numeric"` (telefon nabídne kód z SMS nad klávesnicí).
