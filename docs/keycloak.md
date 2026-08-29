# Keycloak SSO — technický popis integrace

Tento dokument popisuje, jak přesně aplikace (postavená na fancyadmin) komunikuje s Keycloak serverem — jaké OAuth2/OIDC flows používá, jaké endpointy volá a jaké endpointy sama vystavuje. Určeno pro security review a pro provozovatele vlastního Keycloak serveru.

Návod na integraci na straně projektu je v [README.md](../README.md), sekce **18. Keycloak SSO integrace**.

---

## Obsah

1. [Přehled použitých flows](#1-přehled-použitých-flows)
2. [Endpointy Keycloaku, které aplikace volá](#2-endpointy-keycloaku-které-aplikace-volá)
3. [Endpointy, které aplikace vystavuje](#3-endpointy-které-aplikace-vystavuje)
4. [Flow: Přihlášení](#4-flow-přihlášení)
5. [Flow: Odhlášení](#5-flow-odhlášení)
6. [Flow: Backchannel logout](#6-flow-backchannel-logout)
7. [Flow: Změna hesla](#7-flow-změna-hesla)
8. [Frontend — správa session v prohlížeči](#8-frontend--správa-session-v-prohlížeči)
9. [Admin API — správa uživatelů](#9-admin-api--správa-uživatelů)
10. [Bezpečnostní mechanismy](#10-bezpečnostní-mechanismy)
11. [Dvoufázové ověření WebAuthn klíčem](#11-dvoufázové-ověření-webauthn-klíčem)

---

## 1. Přehled použitých flows

| Flow | Účel | Klient |
|---|---|---|
| **Authorization Code Flow s PKCE (S256)** | Přihlášení uživatele | confidential |
| **Authorization Code Flow s PKCE a `prompt=none`** | Silent SSO check (ověření existující KC session bez interakce) | confidential |
| **Client Credentials Grant** | Získání admin tokenu pro Admin API | confidential (service account) |
| **OIDC Backchannel Logout** | Invalidace aplikační session při odhlášení v KC | confidential |
| **Application-Initiated Action (`kc_action=UPDATE_PASSWORD`)** | Změna hesla uživatele v KC z aplikace | confidential |
| **check-sso + silent iframe (keycloak-js, PKCE S256)** | Frontend monitoring KC session, token refresh | public |

Aplikace **nepoužívá**: Implicit Flow, Direct Access Grants (ROPC), offline tokeny. Implementace odpovídá požadavkům OAuth 2.1 (PKCE u všech autorizačních requestů, `state` jako CSRF ochrana, exact redirect URI matching, žádný ROPC).

---

## 2. Endpointy Keycloaku, které aplikace volá

### Backend (server-to-server, přes `baseUrl`)

| Endpoint | Metoda | Účel |
|---|---|---|
| `/realms/{realm}/protocol/openid-connect/token` | POST | Výměna authorization code za tokeny (`grant_type=authorization_code`); získání admin tokenu (`grant_type=client_credentials`) |
| `/realms/{realm}/protocol/openid-connect/userinfo` | GET | Získání user claims (email, jméno) z access tokenu |
| `/realms/{realm}/protocol/openid-connect/certs` | GET | JWKS — podpisové klíče realmu pro validaci backchannel logout tokenu (cachováno 1 h) |
| `/admin/realms/{realm}/users` | GET | Vyhledání uživatele podle emailu/username (výsledek cachován 1 h) |
| `/admin/realms/{realm}/users` | POST | Vytvoření uživatele |
| `/admin/realms/{realm}/users/{id}` | GET | Získání uživatele podle KC ID (při backchannel logoutu) |
| `/admin/realms/{realm}/users/{id}` | PUT | Aktualizace údajů / enable / disable |
| `/admin/realms/{realm}/users/{id}/reset-password` | PUT | Nastavení hesla |
| `/admin/realms/{realm}/users/{id}/execute-actions-email` | PUT | Odeslání emailu pro reset hesla (`UPDATE_PASSWORD`) |

Všechna Admin API volání jsou autentizována Bearer tokenem získaným přes client_credentials grant. Timeout HTTP klienta je 5 s.

### Browser redirecty (přes `hostUrl`)

| Endpoint | Účel |
|---|---|
| `/realms/{realm}/protocol/openid-connect/auth` | Autorizační endpoint — přihlášení, silent check (`prompt=none`), změna hesla (`kc_action=UPDATE_PASSWORD`) |
| `/realms/{realm}/protocol/openid-connect/logout` | RP-initiated logout (s `id_token_hint`, `post_logout_redirect_uri`, `client_id`, `state`) |

Autorizační request vždy obsahuje: `client_id`, `response_type=code`, `redirect_uri`, `scope=openid email profile`, `state` (náhodný CSRF token vázaný na session), `code_challenge` + `code_challenge_method=S256` (PKCE), volitelně `login_hint` (předvyplnění emailu), `ui_locales`, `kc_action`.

Návratová URL se do Keycloaku neposílá — drží se v serverové session pod klíčem `state` a callback si ji vyzvedne po ověření state. `redirect_uri` jsou proto statická a v konfiguraci KC klienta se vyjmenovávají jako exact matches (bez wildcardů).

---

## 3. Endpointy, které aplikace vystavuje

Všechny jsou v routě `keycloak-auth/*` resp. `keycloak-log/*`:

| Endpoint | Metoda | Volá | Účel |
|---|---|---|---|
| `/keycloak-auth/callback?instance={name}&code=...&state=...` | GET | prohlížeč (redirect z KC) | OAuth2 callback — ověření state proti session, výměna code za tokeny (s PKCE verifierem), přihlášení uživatele |
| `/keycloak-auth/silent-check?instance={name}&code=...&state=...` | GET | prohlížeč (redirect z KC) | Callback pro silent SSO check (`prompt=none`), stejná validace state/PKCE |
| `/keycloak-auth/post-log-out?state=...` | GET | prohlížeč (redirect z KC) | Návrat po logoutu z KC, redirect na `state` |
| `/keycloak-auth/backchannel-logout?instance={name}` | POST | **Keycloak server** | OIDC backchannel logout — přijímá `logout_token` (JWT) |
| `/keycloak-auth/silent-check-sso` | GET | prohlížeč (iframe keycloak-js) | Stránka pro silent check iframe adapteru |
| `/keycloak-log/out` | GET | prohlížeč | Mezistránka logoutu (spinner + JS redirect na KC logout) |

> **Důležité pro síťovou konfiguraci:** endpoint `backchannel-logout` volá **Keycloak server přímo** (server-to-server POST). Musí být dostupný z Keycloak serveru. Ostatní endpointy volá jen prohlížeč uživatele.

---

## 4. Flow: Přihlášení

```
uživatel                 aplikace                        Keycloak
   │                        │                               │
   │ 1. zadá email          │                               │
   │───────────────────────►│                               │
   │                        │ 2. lookup: má identita        │
   │                        │    (nebo její role) SSO?      │
   │                        │                               │
   │ 3. redirect na /auth (login_hint=email, state=CSRF token,
   │    code_challenge=S256; návratová URL zůstává v session)
   │────────────────────────────────────────────────────────►
   │                        │                               │
   │ 4. přihlášení v KC     │                               │
   │◄───────────────────────────────────────────────────────│
   │                        │                               │
   │ 5. redirect na /keycloak-auth/callback?code=...&state=...&instance=...
   │───────────────────────►│                               │
   │                        │ 5a. ověření state proti session (CSRF)
   │                        │                               │
   │                        │ 6. POST /token                │
   │                        │  (authorization_code + client_secret + code_verifier)
   │                        │──────────────────────────────►│
   │                        │◄──────────────────────────────│
   │                        │  access_token, id_token       │
   │                        │                               │
   │                        │ 7. GET /userinfo              │
   │                        │──────────────────────────────►│
   │                        │◄──────────────────────────────│
   │                        │  email, given_name, ...       │
   │                        │                               │
   │                        │ 8. párování na lokální        │
   │                        │    identitu podle emailu,     │
   │                        │    vytvoření app session      │
   │ 9. redirect na návratovou URL ze session               │
   │◄───────────────────────│                               │
```

Klíčové body:

- **`state` je náhodný jednorázový CSRF token** — při startu flow se uloží do serverové session (spolu s PKCE code_verifierem a návratovou URL), callback ho ověří a zneplatní; neznámý/expirovaný state (TTL 10 min) flow ukončí. Podvržený callback s cizím authorization code tak nelze do session oběti injektovat.
- **PKCE (S256)** — code_verifier drží serverová session, k token requestu se přikládá při výměně code za tokeny
- **Párování uživatele probíhá podle emailu** — email z KC userinfo se hledá v lokální tabulce `identity`
- Pokud lokální identita neexistuje a je zapnutá auto-registrace, vytvoří se s výchozí rolí z konfigurace SSO instance
- Pokud `/userinfo` selže, claims se čtou fallbackem z `id_token` (bez validace podpisu — jde o data z přímé TLS-ověřené komunikace s KC, ne od uživatele)
- `id_token` se ukládá do serverové session (pro pozdější logout s `id_token_hint`), **access/refresh token backend neukládá** — aplikační session je od té chvíle nezávislá, s výjimkou backchannel logoutu a frontend monitoringu (viz níže)

## 5. Flow: Odhlášení

1. Uživatel klikne na odhlásit → aplikace ukončí lokální session
2. Pokud se uživatel přihlásil přes SSO (v session je `id_token`), aplikace sestaví KC logout URL:
   ```
   {hostUrl}/realms/{realm}/protocol/openid-connect/logout
       ?post_logout_redirect_uri={app}/keycloak-auth/post-log-out
       &id_token_hint={id_token}
       &client_id={clientId}
       &state={návratová URL}
   ```
3. Prohlížeč je přesměrován přes mezistránku `/keycloak-log/out` na KC logout endpoint
4. KC ukončí SSO session a přesměruje zpět na `/keycloak-auth/post-log-out`, který uživatele vrátí na login stránku

## 6. Flow: Backchannel logout

Při ukončení KC session z jakéhokoli důvodu (odhlášení z jiné aplikace, expirace SSO session, deaktivace uživatele adminem):

1. Keycloak pošle `POST /keycloak-auth/backchannel-logout?instance={name}` s tělem `logout_token={JWT}`
2. Aplikace token **plně zvaliduje podle OIDC Back-Channel Logout 1.0**:
   - podpis proti JWKS realmu (`/certs`, cachováno 1 h, při rotaci klíčů se cache jednou obnoví)
   - `iss` (issuer realmu), `aud` (client_id aplikace), `exp`/`iat`
   - `events` musí obsahovat `http://schemas.openid.net/event/backchannel-logout`
   - `nonce` nesmí být přítomen, `sub`/`sid` musí
   - `jti` se přijme jen jednou (replay ochrana)
3. Z validovaného tokenu přečte claim `sub` (KC user ID)
4. Přes Admin API (`GET /admin/realms/{realm}/users/{sub}`) zjistí email uživatele
5. Najde lokální identitu podle emailu a **invaliduje všechny její aplikační sessions**
6. Odpoví `200 OK` (chybové stavy: `400` při chybějícím/nevalidním tokenu nebo neznámé instanci)

## 7. Flow: Změna hesla

Aplikace nikdy nezpracovává staré ani nové heslo — vše probíhá v KC:

1. Aplikace přesměruje uživatele na autorizační endpoint s `kc_action=UPDATE_PASSWORD`
2. KC si vyžádá re-autentizaci současným heslem, ohlídá password policy a 2FA
3. Po změně KC přesměruje zpět na `/keycloak-auth/callback` s `kc_action_status=success`
4. Aplikace propíše success stav do návratové URL (parametr `kcActionSuccess=1`)

Alternativně reset hesla emailem: aplikace zavolá Admin API `execute-actions-email` s akcí `UPDATE_PASSWORD` — email s odkazem posílá **Keycloak** (vyžaduje nakonfigurované SMTP v realmu).

## 8. Frontend — správa session v prohlížeči

Po přihlášení přes SSO běží v prohlížeči adapter `keycloak-js` (oficiální KC adapter) proti **public klientovi**:

- Inicializace s `onLoad: 'check-sso'` + silent check iframe (`/keycloak-auth/silent-check-sso`)
- **Session status iframe** (`checkLoginIframe`) — kontrola stavu KC session každých 30 s; při detekci odhlášení (`onAuthLogout`) okamžitý redirect na logout aplikace
- **Token refresh** — každých 30 s, pokud tokenu zbývá < 60 s platnosti; při selhání refresh (KC session skončila) odhlášení z aplikace
- **Page Visibility API** — ve skryté záložce se refresh pozastavuje, aby neaktivní záložka neudržovala KC session naživu (SSO Session Idle timeout reálně tiká); při návratu do záložky se token hned obnoví, případně se uživatel odhlásí

Frontend tak zajišťuje, že platnost aplikační session je fakticky svázána s platností KC SSO session.

## 9. Admin API — správa uživatelů

Aplikace používá KC Admin API pro synchronizaci uživatelů (volitelné, dle využití v konkrétním projektu):

- vyhledání uživatele podle emailu (s 1h cache na straně aplikace)
- vytvoření uživatele (s heslem, s dočasným heslem, nebo s vynucením nastavení hesla při prvním přihlášení)
- aktualizace údajů (email, jméno, příjmení)
- enable/disable uživatele
- nastavení hesla
- odeslání reset-password emailu

**Vyžadovaná oprávnění service accountu: pouze `manage-users`** z klienta `realm-management`. Aplikace nepotřebuje žádná realm-admin ani jiná oprávnění.

## 10. Bezpečnostní mechanismy

- **PKCE (S256) na všech autorizačních requestech** — backend confidential client i frontend keycloak-js public client; chrání proti code interception a code injection (vyžadováno OAuth 2.1)
- **`state` jako CSRF ochrana** — náhodný jednorázový token vázaný na serverovou session (TTL 10 min, one-time use); návratová URL se nepřenáší přes Keycloak, drží se v session
- **Confidential client** — výměna code za token probíhá výhradně server-to-server s client_secret; tokeny nikdy neprochází prohlížečem (kromě tokenů public klienta pro keycloak-js, což je standardní model KC)
- **TLS validace** — backend komunikace s KC má zapnutou validaci TLS certifikátu (vypnutí je možné jen explicitní config volbou pro lokální vývoj)
- **redirect_uri validace** — `redirect_uri` jsou statická (bez proměnlivých parametrů) a v konfiguraci KC klienta whitelistovaná jako **exact matches bez wildcardů**; při výměně code za token se posílá totožné `redirect_uri` jako v autorizačním requestu (KC ho validuje)
- **Loop detection** — callback endpoint má ochranu proti redirect smyčce (max 3 pokusy o autentizaci za 120 s, poté redirect na login stránku)
- **Ochrana proti open redirectu** — návratové URL pochází výhradně ze serverové session (generované aplikací); post-logout `state` se navíc validuje na shodu hostu s aplikací
- **Backchannel logout** — `logout_token` se plně validuje podle OIDC spec (podpis proti JWKS, iss, aud, events, replay ochrana přes jti); identita uživatele se navíc ověřuje zpětným dotazem na KC Admin API (`sub` → uživatel → email)
- **Žádné ukládání tokenů v DB** — `id_token` je pouze v serverové session (pro logout), access/refresh tokeny backend nedrží
- **Druhý faktor plně v KC** — WebAuthn ceremonie i credentials jsou na straně Keycloaku; aplikace klíče nevidí, neukládá a nijak s nimi nepracuje, jejich správa probíhá výhradně v Keycloaku (viz sekce 11)

---

## 11. Dvoufázové ověření WebAuthn klíčem

Volitelný druhý faktor pro SSO uživatele, řešený čistě konfigurací Keycloak flow. Kdo si klíč nezaregistruje, přihlašuje se dál jen heslem — o volitelnost se stará conditional subflow v KC, ne aplikace. Aplikace do 2FA nijak nezasahuje; registraci i odebírání klíčů řeší administrátor v administraci Keycloaku.

### Konfigurace realmu

| Kde | Nastavení |
|---|---|
| Authentication → Policies → **WebAuthn Policy** | `Relying Party ID` = doména KC serveru (bez schématu a portu), `Require Resident Key` = `No`, `User Verification` = `preferred`, `Signature Algorithms` = `ES256` (+`RS256`) |
| Authentication → Flows | kopie `browser` flow, do subflow `browser forms` za `Username Password Form` přidat **conditional subflow** s `Condition - user configured` + `WebAuthn Authenticator` (Required) |
| Clients → confidential client → Advanced | `Authentication flow overrides → Browser Flow` = nový flow (omezí 2FA jen na tuto aplikaci) |
| Authentication → Required Actions | `Webauthn Register`: `Enabled` = On, `Set as default action` = **Off** (jinak si klíč musí zaregistrovat každý nový uživatel a 2FA přestane být volitelná) |

Provozní poznámky:

- **`Relying Party ID` nelze později změnit** bez zneplatnění všech registrovaných klíčů. KC musí běžet na HTTPS (nebo `localhost`) — WebAuthn v nezabezpečeném kontextu nefunguje.
- **Ztráta klíče = zablokovaný účet.** Doporučuje se povolit `Recovery Authentication Codes`, nebo dát `OTP Form` do conditional subflow jako *Alternative*. Bez záložního faktoru musí klíč odebrat administrátor v administraci Keycloaku.
- Silent SSO (`prompt=none`) i backchannel logout fungují bez změny — druhý faktor se řeší jen při vytváření KC session.
- CSP aplikace se nemění: WebAuthn ceremonie běží na doméně KC, ne na doméně aplikace.

### Správa klíčů

Registraci klíče spustí administrátor tak, že uživateli v administraci Keycloaku (detail uživatele → **Required user actions**) přiřadí akci `Webauthn Register`, uživatel si pak klíč zaregistruje při příštím přihlášení. Odebrání klíče provede administrátor tamtéž (detail uživatele → **Credentials**). Chce-li uživatel 2FA zapnout, vypnout nebo vyměnit klíč, obrací se na administrátora.

Aplikace v tom nehraje žádnou roli: projekt nepotřebuje žádnou migraci, entitu ani další glue třídy a v databázi aplikace se o klíčích neukládá nic.
