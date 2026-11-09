# Push změn do otevřených stránek (Mercure)

Stránka se o změně dozví hned, místo aby se každých pár sekund ptala serveru (kalendář,
úkoly, notifikace, chat). Push jde přes [Mercure](https://mercure.rocks) hub - samostatný
nebo vestavěný ve FrankenPHP. Bez hubu se nic nemění: `NullPush` nic neposílá, stránky pollují.

## Server

Služba `ADT\FancyAdmin\Model\Push\Push`. Fancyadmin zaregistruje `NullPush`, pokud projekt
nemá vlastní. S hubem:

```neon
services:
	- ADT\FancyAdmin\Model\Push\MercurePush(
		publishUrl: %env.MERCURE_PUBLISH_URL%        # interně, např. http://frankenphp:8080/.well-known/mercure
		subscribeUrl: /.well-known/mercure           # pro prohlížeč, přes proxy webu
		publisherKey: %env.MERCURE_PUBLISHER_JWT_KEY%
		subscriberKey: %env.MERCURE_SUBSCRIBER_JWT_KEY%
		topicPrefix: https://example.com/projekt     # hub může sdílet víc projektů - prefix je odděluje
		issuer: %env.MERCURE_ISSUER%
		audience: %env.MERCURE_RESOURCE%
	)
```

**Víc serverů za jednou doménou** (proxy rozkládá požadavky): hub na každém serveru, nginx
každého serveru posílá `/.well-known/mercure` na svůj hub a `publishUrl` je seznam hubů všech
serverů oddělený čárkou (např. přes porty nginx serverů) - změna jde do každého hubu, takže dojde
i odběratelům připojeným přes jiný server. Publikovat jde jen s JWT podepsaným klíčem vydavatele.

Změnu oznámí kód, který ji udělal (typicky Doctrine listener po `flush`):

```php
$this->push->publish('calendar', ['date' => '2026-11-08']);
$this->push->publish('user/' . $assignee->getId() . '/tasks');
```

Data jsou jen nápověda, co se změnilo - stránka si obsah stejně načte svým běžným požadavkem
(s kontrolou oprávnění). Citlivý obsah do pushe nepatří.

Selhání publikování se jen zaloguje (Tracy, warning) - stránka se o změně dozví dalším pollingem.

## Které topiky stránka odebírá

```php
protected function getPushTopics(): array
{
	return ['calendar', 'user/' . $this->getUser()->getId()];
}
```

`BasePresenterTrait` z nich udělá odběr do `body[data-fancyadmin-push]` - token dovolí odebírat
jen tyhle topiky (s prefixem projektu), platí 12 h. Odběr topiku dostává i jeho podtopiky
na straně prohlížeče (`onPush('user/5', …)` dostane `user/5/tasks`), hub ale posílá jen
topiky z tokenu - podtopiky proto publikuj na přesný topik odběru, nebo je do odběru přidej.

## Prohlížeč

```js
import { onPush, onPushState, isPushConnected } from '@fancyadmin/js/push';

onPush('calendar', () => calendar.refetchEvents());

// Polling jen bez pushe
setInterval(() => { if (!isPushConnected()) refresh(); }, 30000);
onPushState((connected) => { if (!connected) refresh(); });   // po výpadku dohnat zmeškané
```

**Vlastní změny stránka nedostane.** Stránka, která změnu udělala, si ji obnoví sama (kalendář po
uložení) - push o ní by ji obnovil podruhé. `push.js` posílá s každým AJAXem přes jQuery ID stránky
(hlavička `X-Fancyadmin-Push-Client`), `MercurePush` ho přidá do zprávy (`origin`) a stránka zprávu
se svým ID přeskočí. Ostatní stránky a záložky téhož uživatele ji dostanou. Požadavky přes `fetch`
hlavičku nemají - po nich přijde push i stránce, která změnu udělala.

Jedno spojení na stránku (fetch se streamem, token v hlavičce `Authorization` - hub ho v URL
ani cookie nepřijme). Po výpadku se připojí znovu (1 s až 30 s). Každá zpráva je i DOM událost
`fancyadmin:push` (`event.detail`).
