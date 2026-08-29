# Integrace adt/fancyadmin do Nette projektu

Tento dokument popisuje krok za krokem, jak integrovat balíček `adt/fancyadmin` do nového Nette 3.x projektu.

---

## Předpoklady

Projekt musí mít nainstalováno:
- PHP >= 8.4
- Nette 3.1+
- Nettrine ORM (`nettrine/orm ^0.10`, `nettrine/dbal ^0.10`)
- Nettrine Migrations (`nettrine/migrations ^0.10`)
- `kdyby/autowired ^3.1`
- `contributte/console ^0.10`
- MySQL 8.0

---

## 1. Composer require

```bash
composer require adt/fancyadmin:^1.0
```

Fancyadmin automaticky stáhne tyto závislosti:
- `adt/doctrine-authenticator` — autentizace přes Doctrine
- `adt/doctrine-components` — BaseEntity, QueryObject, EntityManager
- `adt/doctrine-forms` — formuláře napojené na Doctrine entity
- `adt/nette-forms-components` — rozšířené formulářové prvky
- `adt/datagrid-components` — datagridy
- `adt/files` — správa souborů
- `adt/doctrine-loggable` — audit log
- `contributte/translation` — překlady
- `nette/forms`, `nette/security`, `nette/mail`
- `ublaboo/datagrid`

Doplňkově doporučeno:
```bash
composer require adt/doctrine-components:^3.2 adt/query-object-data-source:^3.0
```

---

## 2. BaseEntity

Vytvořte abstraktní BaseEntity, od které budou dědit všechny entity:

```php
// app/Model/Entities/Abstract/BaseEntity.php
<?php

declare(strict_types=1);

namespace App\Model\Entities\Abstract;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineComponents\Entities\Traits\Identifier;
use Doctrine\ORM\Mapping\MappedSuperclass;

#[MappedSuperclass]
abstract class BaseEntity implements Entity
{
	use Identifier;
}
```

Trait `Identifier` poskytuje:
- `$id` (int, auto-increment PK)
- `getId(): ?int`
- `isNew(): bool`

---

## 3. Entity

Fancyadmin vyžaduje 9 entit. Každá:
- dědí z `BaseEntity`
- implementuje interface z `ADT\FancyAdmin\Model\Entities`
- používá odpovídající trait, který poskytuje sloupce, vztahy a metody

### 3.1 Identity (hlavní uživatelská entita)

```php
// app/Model/Entities/Identity.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Identity extends BaseEntity
    implements \ADT\FancyAdmin\Model\Entities\Identity,
               \ADT\DoctrineAuthenticator\OTP\Identity
{
	use IdentityTrait;
}
```

**IdentityTrait poskytuje:**
- Sloupce: `firstName`, `lastName`, `email`, `username`, `password`, `phoneNumber`, `context`, `isActive`
- Timestamps: `createdAt`, `updatedAt`, `createdBy`, `updatedBy`
- Vztahy: `profiles` (1:N), `roles` (M:N s AclRole), `selectedAccount` (N:1)
- Metody: `getFullName()`, `getRoles()`, `isAllowed()`, `isAdmin()`, `getGravatar()`
- Auth metody: `getAuthObjectId()`, `getAuthToken()`, `setAuthToken()`, `setPassword()` (automaticky hashuje)

### 3.2 Account

```php
// app/Model/Entities/Account.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\AccountTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Account extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Account
{
	use AccountTrait;

	public function __construct()
	{
		$this->accounts = new ArrayCollection();
	}
}
```

**AccountTrait poskytuje:** `name`, `parent` (self-ref), `accounts` (sub-accounts), timestamps

### 3.3 Profile

```php
// app/Model/Entities/Profile.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\ProfileTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Profile extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Profile
{
	use ProfileTrait;
}
```

**ProfileTrait poskytuje:** `identity` (N:1), `account` (N:1), `roles` (M:N s AclRole), `isActive`, timestamps

### 3.4 AclRole

```php
// app/Model/Entities/AclRole.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\AclRoleTrait;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AclRole extends BaseEntity
    implements \ADT\FancyAdmin\Model\Entities\AclRole,
               CreatedByNullableInterface,
               UpdatedByInterface
{
	use AclRoleTrait;
}
```

**AclRoleTrait poskytuje:** `name`, `type` (AclRoleTypeEnum), `context`, `isAdmin`, `acls` (1:N), metody `isAllowed()`, `getResources()`, `getRoleId()`

### 3.5 AclResource

```php
// app/Model/Entities/AclResource.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\AclResourceTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AclResource extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\AclResource
{
	use AclResourceTrait;
}
```

**AclResourceTrait poskytuje:** `name` (unique), `title`

### 3.6 Acl (vazba role-resource)

```php
// app/Model/Entities/Acl.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\AclTrait;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByInterface;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Acl extends BaseEntity
    implements \ADT\FancyAdmin\Model\Entities\Acl,
               CreatedByInterface,
               UpdatedByInterface
{
	use AclTrait;
}
```

**AclTrait poskytuje:** `role` (N:1 AclRole), `resource` (N:1 AclResource), `isActive`, timestamps

### 3.7 Configuration

```php
// app/Model/Entities/Configuration.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\ConfigurationTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Configuration extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Configuration
{
	use ConfigurationTrait;
}
```

### 3.8 File

```php
// app/Model/Entities/File.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\Files\Entities\FileTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class File extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\File
{
	use FileTrait;
}
```

### 3.9 GridFilter

```php
// app/Model/Entities/GridFilter.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class GridFilter extends BaseEntity
{
	use \ADT\FancyAdmin\Model\Entities\GridFilter;
}
```

---

## 4. ACL Resource Enum

Definujte enum s ACL resources. Fancyadmin vyžaduje minimálně 3:
- customer resource (přístup do zákaznické části)
- backoffice resource (přístup do administrace)
- full data resource (plný přístup k datům)

```php
// app/Model/Entities/Enums/AclResourceNameEnum.php
<?php

declare(strict_types=1);

namespace App\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case CUSTOMER_HOME = 'portalCustomer.home';
	case BACKOFFICE_HOME = 'portalBackoffice.home';
	case FULL_DATA = 'portal.fullData';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
```

---

## 5. Query třídy

Fancyadmin vyžaduje QueryObject pattern z `adt/doctrine-components`. Každý query objekt:
- dědí z BaseQuery (rozšiřuje `ADT\DoctrineComponents\QueryObject\QueryObject`)
- implementuje interface z fancyadmin
- používá odpovídající trait z fancyadmin

### 5.1 BaseQuery

```php
// app/Model/Queries/Abstract/BaseQuery.php
<?php

declare(strict_types=1);

namespace App\Model\Queries\Abstract;

use ADT\Components\AjaxSelect\Interfaces\OrByIdFilterInterface;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQueryTrait;

/**
 * @extends QueryObject<TEntity>
 * @template TEntity of object
 */
abstract class BaseQuery extends QueryObject implements OrByIdFilterInterface, \ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery
{
	use BaseQueryTrait;
}
```

### 5.2 Konkrétní Query třídy

Vzor je pro všechny stejný — implementovat interface, použít trait, přidat stub metody:

```php
// app/Model/Queries/IdentityQuery.php
<?php

declare(strict_types=1);

namespace App\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\IdentityQueryTrait;
use App\Model\Entities\Identity;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends Abstract\BaseQuery<Identity>
 */
class IdentityQuery extends Abstract\BaseQuery
    implements \ADT\FancyAdmin\Model\Queries\IdentityQuery,
               \ADT\DoctrineAuthenticator\OTP\IdentityQuery
{
	use IdentityQueryTrait;

	protected function applySecurityFilter(): void {}
	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void {}
	protected function setDefaultOrder(): void {}
}
```

Stejný vzor pro:
- **AccountQuery** — `use AccountQueryTrait; implements \ADT\FancyAdmin\Model\Queries\AccountQuery`
- **ProfileQuery** — `use ProfileQueryTrait; implements \ADT\FancyAdmin\Model\Queries\ProfileQuery`
- **AclRoleQuery** — `use AclRoleQueryTrait; implements \ADT\FancyAdmin\Model\Queries\AclRoleQuery` (+ `applyAccountFilter`)
- **ConfigurationQuery** — `use ConfigurationQueryTrait; implements \ADT\FancyAdmin\Model\Queries\ConfigurationQuery`
- **GridFilterQuery** — `use \ADT\Datagrid\Model\Queries\GridFilterQueryTrait; implements \ADT\Datagrid\Model\Queries\GridFilterQuery`

### 5.3 DefaultFilters trait

```php
// app/Model/Queries/Filters/DefaultFilters.php
<?php

namespace App\Model\Queries\Filters;

trait DefaultFilters
{
	use \ADT\FancyAdmin\Model\Queries\Filters\DefaultFilters;
}
```

---

## 6. Query Factory interfaces

Každá Query třída potřebuje factory interface pro DI autowiring. Factory interface rozšiřuje fancyadmin factory a upřesňuje return type:

```php
// app/Model/Queries/Factories/IdentityQueryFactory.php
<?php

namespace App\Model\Queries\Factories;

use App\Model\Queries\IdentityQuery;

interface IdentityQueryFactory extends \ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory
{
	public function create(): IdentityQuery;
}
```

Vytvořte factory pro každou query: `AccountQueryFactory`, `AclRoleQueryFactory`, `ConfigurationQueryFactory`, `ProfileQueryFactory`, `GridFilterQueryFactory`.

**Registrace v config:** Query factories se registrují automaticky přes `search` v neon:

```neon
search:
	queries:
		in: %appDir%/Model/Queries
		files:
			- *Factory.php
```

---

## 7. Security — Authenticator

```php
// app/Model/Security/Authenticator.php
<?php

namespace App\Model\Security;

use ADT\DoctrineAuthenticator\OTP\OnetimeTokenAuthenticator;
use ADT\FancyAdmin\Model\Security\AuthenticatorTrait;

class Authenticator extends OnetimeTokenAuthenticator
    implements \ADT\FancyAdmin\Model\Security\Authenticator
{
	use AuthenticatorTrait;
}
```

`OnetimeTokenAuthenticator` rozšiřuje `DoctrineAuthenticator` a přidává OTP podporu. `AuthenticatorTrait` přidává `validateIdentity()` kontrolu ACL.

**Poznámka:** Pokud nepotřebujete OTP, můžete rozšiřovat přímo `DoctrineAuthenticator` a implementovat `verifyCredentials()`.

---

## 8. Security — SecurityUser

```php
// app/Model/Security/SecurityUser.php
<?php

namespace App\Model\Security;

use ADT\FancyAdmin\Model\Security\SecurityUserTrait;
use App\Model\Entities\Identity;

/**
 * @method Identity getIdentity()
 */
class SecurityUser extends \ADT\DoctrineAuthenticator\SecurityUser
    implements \ADT\FancyAdmin\Model\Security\SecurityUser
{
	use SecurityUserTrait;
}
```

**Důležité:** Rozšiřuje `ADT\DoctrineAuthenticator\SecurityUser` (ne `Nette\Security\User` přímo), protože ten má kompatibilní (ne-final) `getAuthorizator()`.

---

## 9. Security — Permission

```php
// app/Model/Security/Permission.php
<?php

namespace App\Model\Security;

class Permission extends \ADT\FancyAdmin\Model\Security\Permission
{
}
```

---

## 10. Doctrine — EntityManager

```php
// app/Model/Doctrine/EntityManager.php
<?php

declare(strict_types=1);

namespace App\Model\Doctrine;

class EntityManager extends \ADT\DoctrineComponents\EntityManager
{
}
```

---

## 11. Listeners

Fancyadmin potřebuje 3 event listenery pro automatické nastavování `createdBy`, `account` a `selectedAccount`:

```php
// app/Model/Listeners/Abstract/BaseListener.php
<?php

declare(strict_types=1);

namespace App\Model\Listeners\Abstract;

abstract class BaseListener extends \ADT\DoctrineComponents\BaseListener
{
}
```

```php
// app/Model/Listeners/CreatedByEntityBaseListener.php
<?php

declare(strict_types=1);

namespace App\Model\Listeners;

use ADT\FancyAdmin\Model\Listeners\CreatedByListenerTrait;
use App\Model\Listeners\Abstract\BaseListener;

class CreatedByEntityBaseListener extends BaseListener
{
	use CreatedByListenerTrait;
}
```

```php
// app/Model/Listeners/AccountFieldBaseListener.php
<?php

declare(strict_types=1);

namespace App\Model\Listeners;

use ADT\FancyAdmin\Model\Listeners\AccountFieldListenerTrait;
use App\Model\Listeners\Abstract\BaseListener;

class AccountFieldBaseListener extends BaseListener
{
	use AccountFieldListenerTrait;
}
```

```php
// app/Model/Listeners/SelectAccountListener.php
<?php

declare(strict_types=1);

namespace App\Model\Listeners;

use ADT\FancyAdmin\Model\Listeners\SelectAccountListenerTrait;
use App\Model\Listeners\Abstract\BaseListener;

class SelectAccountListener extends BaseListener
{
	use SelectAccountListenerTrait;
}
```

**Registrace v config:**
```neon
search:
	listeners:
		in: %appDir%/Model/Listeners
		files:
			- *Listener.php
```

---

## 12. Translator

```php
// app/Model/Translator.php
<?php

declare(strict_types=1);

namespace App\Model;

class Translator extends \Contributte\Translation\Translator
{
}
```

---

## 13. Router

FancyAdminRouter se integruje do RouterFactory:

```php
// app/Core/RouterFactory.php
<?php

declare(strict_types=1);

namespace App\Core;

use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\Routing\RouteList;

class RouterFactory
{
	public static function create(FancyAdminRouter $fancyAdminRouter): RouteList
	{
		$router = new RouteList();

		// Fancyadmin routes (Sign:in, Sign:out, portal routes)
		$router[] = $fancyAdminRouter->getRouteList();

		// Web module routes
		$webModule = new RouteList('Web');
		$webModule->addRoute('<presenter>/<action>[/<id>]', [
			'presenter' => 'Home',
			'action' => 'default',
		]);
		$router[] = $webModule;

		return $router;
	}
}
```

---

## 14. Portal Presentery

Fancyadmin poskytuje presenter traity pro portálovou část (admin):

### BasePresenter

```php
// app/UI/Portal/Presenters/BasePresenter.php
<?php

namespace App\UI\Portal\Presenters;

use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\UI\Presenter;

class BasePresenter extends Presenter
{
	use AutowireComponentFactories;
	use AutowireProperties;
	use BasePresenterTrait {
		BasePresenterTrait::beforeRender as traitBeforeRender;
	}
}
```

### AuthPresenter (abstraktní — base pro všechny presentery vyžadující přihlášení)

```php
// app/UI/Portal/Presenters/AuthPresenter.php
<?php

namespace App\UI\Portal\Presenters;

use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;
use App\Model\Security\SecurityUser;

/**
 * @method SecurityUser getUser()
 */
abstract class AuthPresenter extends BasePresenter
    implements \ADT\FancyAdmin\UI\Presenters\AuthPresenter
{
	use AuthPresenterTrait;
}
```

---

## 15. NEON konfigurace

### common.neon — extensions

```neon
extensions:
	autowired: Kdyby\Autowired\DI\AutowiredExtension
	translation: Contributte\Translation\DI\TranslationExtension
	nettrine.dbal: ADT\DoctrineComponents\DI\DbalExtension
	nettrine.orm: Nettrine\ORM\DI\OrmExtension
	nettrine.extensions.atlantic18: Nettrine\Extensions\Atlantic18\DI\Atlantic18BehaviorExtension
	queryObjectDataSource: ADT\QueryObjectDataSource\DI\QueryObjectDataSourceExtension
	fancyadmin: ADT\FancyAdmin\DI\FancyAdminExtension
	datagridComponents: ADT\Datagrid\DI\DataGridComponentsExtension
```

**Poznámka:** DBAL extension je `ADT\DoctrineComponents\DI\DbalExtension` (ne `Nettrine\DBAL\DI\DbalExtension`). Tato extension rozšiřuje Nettrine DBAL o další funkce.

### common.neon — search (auto-registrace services)

```neon
search:
	listeners:
		in: %appDir%/Model/Listeners
		files:
			- *Listener.php
	queries:
		in: %appDir%/Model/Queries
		files:
			- *Factory.php
```

### common.neon — fancyadmin

```neon
fancyadmin:
	project: muj-projekt
	projectName: Můj Projekt
	logoPublicPath: logo.svg
	logoBitmapPublicPath: /images/logo.png
	logoMenuPath: /images/logo.png
	loginPageLogoPath: logo.svg
	context: project
	lostPasswordEnabled: true
	adminHostPath: %env.PORTAL_URL%
	hmr: %hmr%
	customerAclResource: App\Model\Entities\Enums\AclResourceNameEnum::CUSTOMER_HOME
	backofficeAclResource: App\Model\Entities\Enums\AclResourceNameEnum::BACKOFFICE_HOME
	fullDataAclResource: App\Model\Entities\Enums\AclResourceNameEnum::FULL_DATA
	locksDir: %locksDir%
	emailBackgroundColor: '#fff'
	colors:
		backgroundColor: '#f1f7f7'
		dashboardAccentColor: '#9ad0f5'
		primaryColor: '#42b6a4'
		primaryColorDark: '#3fad9c'
		primaryColorDark20: '#3ba494'
		secondaryColor: '#f1f7f7'
		secondaryColorDark: '#e1eeee'
		secondaryColorDarker: '#d2e5e5'
		ternaryColor: '#101D40'
		ternaryTextColor: '#ffffff'
		loginBackground: 'rgb(90, 97, 120)'
		loginBackgroundInput: 'rgb(255, 255, 255, 0.3)'
		loginBackgroundInputFocus: 'rgb(255, 255, 255, 0.4)'
		loginInputTextColor: '#1a1a1a'
		inputBorder: '1px solid #c8c8c8'
		inputFocusBorder: '0'
		inputFocusBackground: '#f0f0f0'
```

### common.neon — ORM mapping

```neon
nettrine.orm:
	managers:
		default:
			connection: default
			entityManagerDecoratorClass: App\Model\Doctrine\EntityManager
			lazyNativeObjects: true
			mapping:
				entities:
					namespace: App\Model\Entities
					directories:
						- %appDir%/Model/Entities
				doctrineAuthenticator:
					namespace: ADT\DoctrineAuthenticator
					directories:
						- %appDir%/../vendor/adt/doctrine-authenticator/src
```

**Důležité:** Mapování `doctrineAuthenticator` je potřeba, protože `ADT\DoctrineAuthenticator` obsahuje entity (StorageEntity, LoginAttempt, OnetimeToken) s Doctrine atributy.

### common.neon — services

```neon
services:
	router: App\Core\RouterFactory::create
	jsComponents: ADT\Utils\JsComponents
	- App\Model\Security\Permission
	security.user: App\Model\Security\SecurityUser
	security.userStorage: Nette\Bridges\SecurityHttp\CookieStorage
	security.authenticator:
		factory: App\Model\Security\Authenticator(expiration: '14 days')
		setup:
			- setFraudDetection(true)
			- setExpirationCallback(Closure::fromCallable(@ADT\FancyAdmin\Model\Security\SessionExpirationCallback))
	- ADT\DoctrineAuthenticator\OTP\OnetimeTokenService
	- ADT\FancyAdmin\Model\Security\SessionExpirationCallback
```

### common.neon — datagrid

```neon
datagridComponents:
	locksDir: %locksDir%
	downloadLink: Portal:Download:gridExport
```

### common.neon — translation

```neon
translation:
	locales:
		default: cs
		whitelist: [cs, en]
		fallback: [cs]
	dirs:
		- %appDir%/lang
	localeResolvers: []
	loaders:
		yml: Symfony\Component\Translation\Loader\YamlFileLoader
	translatorFactory: App\Model\Translator
```

### common.neon — atlantic18 (Gedmo)

```neon
nettrine.extensions.atlantic18:
	timestampable: true
	softDeleteable: true
```

### common.neon — decorator

```neon
decorator:
	App\Model\Queries\Abstract\BaseQuery:
		setup:
			- setSecurityUser(@App\Model\Security\SecurityUser)
```

---

## 16. Migrace

Po nastavení vygenerujte migraci:

```bash
php bin/console migrations:diff
php bin/console migrations:migrate
```

Toto vytvoří tabulky: `identity`, `account`, `profile`, `acl_role`, `acl_resource`, `acl`, `configuration`, `file`, `grid_filter`, `storage_entity` (auth sessions), `login_attempt`, `ext_log_entries` (audit log).

Po migraci vytvořte první identitu:

```bash
php bin/console adt:fancyadmin:create-identity
```

---

## 17. Struktura souborů

```
app/
├── Core/
│   └── RouterFactory.php
├── Model/
│   ├── Doctrine/
│   │   └── EntityManager.php
│   ├── Entities/
│   │   ├── Abstract/
│   │   │   └── BaseEntity.php
│   │   ├── Enums/
│   │   │   └── AclResourceNameEnum.php
│   │   ├── Acl.php
│   │   ├── AclResource.php
│   │   ├── AclRole.php
│   │   ├── Account.php
│   │   ├── Configuration.php
│   │   ├── File.php
│   │   ├── GridFilter.php
│   │   ├── Identity.php
│   │   └── Profile.php
│   ├── Listeners/
│   │   ├── Abstract/
│   │   │   └── BaseListener.php
│   │   ├── AccountFieldBaseListener.php
│   │   ├── CreatedByEntityBaseListener.php
│   │   └── SelectAccountListener.php
│   ├── Queries/
│   │   ├── Abstract/
│   │   │   └── BaseQuery.php
│   │   ├── Factories/
│   │   │   ├── AccountQueryFactory.php
│   │   │   ├── AclRoleQueryFactory.php
│   │   │   ├── ConfigurationQueryFactory.php
│   │   │   ├── GridFilterQueryFactory.php
│   │   │   ├── IdentityQueryFactory.php
│   │   │   └── ProfileQueryFactory.php
│   │   ├── Filters/
│   │   │   └── DefaultFilters.php
│   │   ├── AccountQuery.php
│   │   ├── AclRoleQuery.php
│   │   ├── ConfigurationQuery.php
│   │   ├── GridFilterQuery.php
│   │   ├── IdentityQuery.php
│   │   └── ProfileQuery.php
│   ├── Security/
│   │   ├── Authenticator.php
│   │   ├── Permission.php
│   │   └── SecurityUser.php
│   └── Translator.php
└── UI/
    ├── Portal/
    │   └── Presenters/
    │       ├── AuthPresenter.php
    │       └── BasePresenter.php
    └── Web/
        └── Presenters/
            └── BasePresenter.php
```

---

## 18. Keycloak SSO integrace (volitelné)

Fancyadmin podporuje napojení na jeden nebo více Keycloak serverů/realmů pro SSO autentizaci. Integrace je ve výchozím stavu **vypnutá** a aktivuje se přidáním `keycloak` sekce do konfigurace.

> Technický popis integrace (použité OAuth2/OIDC flows, volané endpointy, bezpečnostní mechanismy) — vhodný pro security review nebo externí partnery provozující vlastní Keycloak — je v [docs/keycloak.md](docs/keycloak.md).

### 18.1 Předpoklady

- Keycloak server s nakonfigurovaným realmem
- Klient v Keycloaku s:
  - **Client authentication**: zapnuto (confidential client)
  - **Service accounts roles**: zapnuto (pro Admin API — vyhledávání a správa uživatelů)
  - **Valid redirect URIs** — pouze exact URIs, žádné wildcardy (OAuth 2.1); `nazev-sso` nahraďte názvem SSO instance (sloupec `name` v Sso entitě):
    - `https://admin.muj-projekt.cz/keycloak-auth/callback?instance=nazev-sso`
    - `https://admin.muj-projekt.cz/keycloak-auth/silent-check?instance=nazev-sso`
  - **Valid post logout redirect URIs**: `https://admin.muj-projekt.cz/keycloak-auth/post-log-out`
  - **Web origins**: `https://admin.muj-projekt.cz`
  - **Require PKCE**: zapnuto, **PKCE Method**: `S256` (Settings → Capability config; server pak request bez `code_challenge` odmítne).
- Service account musí mít roli `manage-users` z `realm-management` clienta
- Druhý (public) klient pro frontend keycloak-js adapter — s **Client authentication: vypnuto**, **Require PKCE** + **PKCE Method** `S256` a **Valid redirect URIs**: `https://admin.muj-projekt.cz/keycloak-auth/silent-check-sso`
- Doporučeno: v realmu aktivovat client policies s vestavěnými profily `oauth-2-1-for-confidential-client` a `oauth-2-1-for-public-client` — Keycloak pak požadavky OAuth 2.1 (PKCE, exact URIs, zakázané granty) vynucuje sám
- `guzzlehttp/guzzle` a `firebase/php-jwt` (validace backchannel logout tokenů) nainstalované v projektu:
  ```bash
  composer require guzzlehttp/guzzle:^7.0 firebase/php-jwt:^6.0
  ```

### 18.2 Sso entita

Fancyadmin vyžaduje entitu `Sso`, která uchovává kompletní konfiguraci Keycloak instancí:

```php
// app/Model/Entities/Sso.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\SsoTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Sso extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Sso
{
    use SsoTrait;
}
```

Po vytvoření entity spusťte migraci.

**SsoTrait poskytuje:**

| Sloupec | Typ | Popis |
|---|---|---|
| `name` | string (unique) | Identifikátor instance |
| `realm` | string | Název Keycloak realmu |
| `baseUrl` | string | Interní URL pro API volání (např. `http://keycloak:8080`) |
| `hostUrl` | string | Veřejná URL pro redirect uživatele (např. `https://auth.muj-projekt.cz`) |
| `clientId` | string | Confidential client ID |
| `clientSecret` | string | Client secret |
| `frontendClientId` | string | Public client ID pro keycloak-js adapter |
| `defaultRole` | AclRole (nullable) | Role, která se přiřadí novému uživateli při SSO registraci — relace na entitu `AclRole` (v DB sloupec `default_role_id`) |

### 18.3 NEON konfigurace

V neonu se Keycloak pouze zapíná/vypíná. Veškerá konfigurace instancí je v tabulce `sso`:

```neon
fancyadmin:
    # ... ostatní konfigurace ...
    keycloakEnabled: true
```

Pokud je `keycloakEnabled` nastaveno na `false` (výchozí), vše Keycloak-related je vypnuté a projekt funguje jako dříve.

Pro lokální vývoj se self-signed certifikátem lze vypnout validaci TLS certifikátu Keycloak serveru volbou `keycloakVerifySsl: false`. Na produkci musí zůstat výchozí `true` — přes tento kanál jde výměna authorization code za tokeny včetně client_secret.

### 18.4 Nastavení SSO v databázi

1. **Vytvořte záznamy v tabulce `sso`** s kompletní konfigurací Keycloak instance:

   | id | name | realm | baseUrl | hostUrl | clientId | clientSecret | frontendClientId | default_role_id |
   |---|---|---|---|---|---|---|---|---|
   | 1 | hlavni | muj-realm | http://keycloak:8080 | https://auth.example.cz | app-client | secret123 | app-public | 5 |

   Kde `default_role_id` je cizí klíč na `acl_role.id` — ID role, která se automaticky přiřadí novému uživateli při prvním SSO přihlášení. Pokud nechcete automatické přiřazení role, nechte `NULL`.

2. **Navažte role na SSO instance** — v tabulce `acl_role` nastavte `sso_id` u rolí, které se mají přihlašovat přes SSO

3. **Volitelně navažte identity** — v tabulce `identity` lze nastavit `sso_id` přímo (má přednost před rolí)

Při zadání emailu na login stránce fancyadmin zjistí SSO instanci z identity (přímo, nebo přes její roli) a přesměruje na odpovídající Keycloak. Pokud identita nemá SSO vazbu, zobrazí se standardní přihlášení heslem.

### 18.5 Presentery

Vytvořte dva presentery pro Keycloak OAuth2 flow:

```php
// app/UI/Portal/Presenters/KeycloakAuth/KeycloakAuthPresenter.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Presenters\KeycloakAuth;

use ADT\FancyAdmin\UI\Presenters\Keycloak\KeycloakAuthPresenterTrait;
use App\UI\Portal\Presenters\BasePresenter;

class KeycloakAuthPresenter extends BasePresenter
{
    use KeycloakAuthPresenterTrait;
}
```

```php
// app/UI/Portal/Presenters/KeycloakLog/KeycloakLogPresenter.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Presenters\KeycloakLog;

use ADT\FancyAdmin\UI\Presenters\Keycloak\KeycloakLogPresenterTrait;
use App\UI\Portal\Presenters\BasePresenter;

class KeycloakLogPresenter extends BasePresenter
{
    use KeycloakLogPresenterTrait;
}
```

### 18.6 JavaScript

V `app.js` projektu přidejte import keycloak adaptéru pro silent SSO check:

```js
import { keycloakLoginSync } from '../path/to/vendor/adt/fancyadmin/assets/js/keycloak';
keycloakLoginSync();
```

Pro keycloak email check na login formuláři importujte modul **eagerly** v `app.js`:

```js
import '../path/to/vendor/adt/fancyadmin/assets/js/signInKeycloak';
```

> **Důležité:** import musí být eager (ne přes `AdtJsComponents.init`, který modul načítá lazy až
> když je formulář na stránce). Modul si při importu naváže delegovaný `change` listener na `document`,
> takže funguje i pro login formulář vložený přes AJAX (např. po odhlášení), aniž by se musel
> reinicializovat. Při lazy načtení by se po AJAX přepnutí na `/sign/in` listener nenavázal.

**Závislost:** Projekt musí mít nainstalovaný npm balíček `keycloak-js`:
```bash
yarn add keycloak-js
```

### 18.7 Co se děje automaticky

Po zapnutí Keycloak konfigurace fancyadmin automaticky:

- **Registruje routy** `keycloak-auth/<action>` a `keycloak-log/<action>` v Portal modulu
- **Login formulář** — přidá `data-keycloak-check-url` atribut na email input; po zadání emailu JS zjistí SSO instanci z identity/role a přesměruje na odpovídající Keycloak
- **Logout** — `Sign:out` automaticky odhlásí i z Keycloaku (pokud se uživatel přihlásil přes SSO)
- **Frontend** — do layoutu injektuje `window.__keycloakSettings` pro keycloak-js adapter (silent SSO check, token refresh)
- **Registrace při SSO** — pokud se přes Keycloak přihlásí uživatel, který v aplikaci neexistuje, automaticky se mu vytvoří identita s vazbou na SSO instanci a `defaultRole` (pokud je nakonfigurovaná). Toto chování zajišťuje `autoRegister: true` v interním volání `loginUser()` — lze přepsat rozšířením třídy `Keycloak` (viz 18.11)

### 18.8 Keycloak služba — správa uživatelů

Keycloak instance jsou dostupné přes `KeycloakManager`:

```php
$manager = $this->_fancyAdmin->getKeycloakManager(); // null pokud je Keycloak vypnutý

// Získat konkrétní instanci podle názvu
$keycloak = $manager->getInstance('hlavni');

// Získat instanci podle identity (z identity.sso nebo role.sso)
$keycloak = $manager->getInstanceForIdentity($identity);

// Získat instanci, přes kterou je přihlášen aktuální uživatel (ze session)
$keycloak = $manager->getInstanceFromSession();
```

Každá instance poskytuje metody pro správu uživatelů přes Admin API:

```php
// Registrace uživatele v Keycloaku (vrací existujícího pokud už existuje)
$keycloakUser = $keycloak->registerUser($identity, 'heslo', temporaryPassword: false);

// Aktualizace údajů (email, jméno, příjmení)
$keycloakUser = $keycloak->updateUser($identity);

// Deaktivace / aktivace
$keycloak->disableUser($identity);
$keycloak->enableUser($identity);

// Nastavení hesla
$keycloak->setUserPassword($identity, 'noveHeslo', temporary: true);

// Vyhledání uživatele podle emailu
$keycloakUser = $keycloak->findUser('user@example.com');

// Odeslání emailu pro reset hesla přes Keycloak (execute-actions-email)
// Keycloak pošle svůj email s odkazem na formulář; po nastavení hesla KC přesměruje na $redirectUri
$keycloak->sendPasswordResetEmail($identity, redirectUri: 'https://admin.muj-projekt.cz/sign/in');
```

Pro přesměrování přihlášeného uživatele na změnu hesla přímo v Keycloaku (Application-Initiated Action) použijte `getUpdatePasswordUrl()` — Keycloak si sám vyžádá re-autentizaci současným heslem, ohlídá password policy i 2FA a po nastavení nového hesla vrátí uživatele zpět do aplikace:

```php
// URL pro přesměrování na změnu hesla v Keycloaku
$url = $keycloak->getUpdatePasswordUrl(
    backRedirect: 'https://admin.muj-projekt.cz/profil',
    loginHint: $identity->getEmail(),
);
$this->redirectUrl($url);
```

### 18.9 Backchannel logout

Keycloak podporuje backchannel logout — při ukončení session v Keycloaku (odhlášení, expirace, deaktivace uživatele) Keycloak pošle POST request na aplikaci, která invaliduje lokální session uživatele.

#### Nastavení v Keycloaku

V Keycloak admin panelu → **Clients** → váš confidential client → **Settings**:

1. **Backchannel logout URL**:
   ```
   https://admin.muj-projekt.cz/keycloak-auth/backchannel-logout?instance=nazev-sso
   ```
   Kde `nazev-sso` odpovídá hodnotě `name` v tabulce `sso`.

2. **Backchannel logout session required**: **On**

Opakujte pro každou SSO instanci s odpovídajícím `?instance=` parametrem.

#### Co se děje

1. Keycloak pošle POST s `logout_token` (JWT) na backchannel URL
2. Aplikace token zvaliduje podle OIDC Back-Channel Logout spec (podpis proti JWKS realmu, iss, aud, events, replay ochrana) — vyžaduje `firebase/php-jwt`; nevalidní token dostane `400`
3. Z tokenu získá `sub` (Keycloak user ID)
4. Přes Admin API zjistí email uživatele
5. Najde lokální identitu podle emailu
6. Invaliduje všechny její sessions (`Authenticator::clearIdentity`)

Tím je zajištěno, že:
- Uživatel odhlášený z Keycloaku je automaticky odhlášen i z aplikace
- Uživatel deaktivovaný v Keycloaku ztrácí přístup okamžitě (session je ukončena a nové SSO přihlášení selže)

### 18.10 Přidání nové Keycloak instance

Postup pro přidání další SSO instance do existujícího projektu:

1. **DB** — vytvořte nový záznam v tabulce `sso` s kompletní konfigurací (realm, URL, credentials)
2. **DB** — u příslušných rolí/identit nastavte vazbu na nové SSO
3. **Keycloak** — v novém clientu nastavte backchannel logout URL (viz 18.9)

Žádná změna PHP kódu, `.env` ani neon konfigurace není potřeba. Instance se vytváří dynamicky z databáze.

### 18.11 Rozšíření chování

Keycloak službu lze rozšířit v projektu — např. pro úpravu logiky vytváření identity při SSO loginu:

```php
class MyKeycloak extends \ADT\FancyAdmin\Model\Security\Keycloak\Keycloak
{
    protected function createIdentity(array $userInfo): Identity
    {
        $identity = parent::createIdentity($userInfo);
        // vlastní logika — přiřazení kontextu, notifikace, atd.
        return $identity;
    }
}
```

Pro použití vlastní třídy je potřeba rozšířit `KeycloakManager::createInstanceFromSso()` v projektu.

---

## 19. Passkeys (WebAuthn)

Fancyadmin podporuje přihlašování přes passkeys (WebAuthn) postavené na knihovně
[lbuchs/webauthn](https://github.com/lbuchs/WebAuthn). Passkeys jsou **opt-in** — zapínají
se configem `passkeyEnabled: true` (default `false`, viz 19.2). Při vypnuté featuře se
nevykresluje tlačítko na login stránce ani karta v Můj účet a všechny passkey operace
jsou zablokované i server-side (`PasskeyService::assertEnabled()`). Existující klíče
v DB při vypnutí zůstávají — po opětovném zapnutí zase fungují. Passkey je vždy jen
alternativa k heslu (žádné passkey-only účty). Klíč si může zaregistrovat i identita
navázaná na Keycloak SSO, aby měla 2FA připravené na dobu, kdy jí SSO bude zrušeno.
Uživatel s povinným Keycloak loginem (SSO instance + role s `needsSso`) se ale přes
passkey nepřihlásí: místo přihlášení ho login formulář natvrdo přesměruje na Keycloak.

Při `passkeyEnabled: false` (default) projekt **nemusí mít žádné passkey třídy** —
entitu, query, factory, form, grid ani passkey trait v Identity (sekce 19.3-19.5);
v tabulce `identity` pak není žádný passkey sloupec. Při `passkeyEnabled: true`
jsou povinné; extension to zvaliduje při kompilaci DI kontejneru a chybějící
infrastrukturu ohlásí srozumitelnou chybou.

Co uživatel dostane:

- **Login stránka** — tlačítko „Přihlásit se přihlašovacím klíčem" (usernameless login,
  prohlížeč nabídne uložené discoverable credentials). Tlačítko je jediná cesta —
  passkey se **nenabízí automaticky** v autofillu email pole (conditional mediation
  není zapnutá)
- **Můj účet** — karta „Přihlašovací klíče": přidání klíče (side panel s povinným názvem),
  smazání, badge pro synchronizované klíče (zálohované u správce passkeys)

### 19.1 Požadavky

- **HTTPS** — WebAuthn funguje jen v secure kontextu (výjimka: `localhost`)
- **rpId = doména admin hostu** — klíče jsou svázané s doménou. Default se odvozuje
  z `adminHostPath`, ale **doporučujeme nastavit `passkeyRpId` explicitně**: jeho pozdější
  změna zneplatní všechny už registrované klíče, takže je to hodnota, kterou chcete mít
  vědomě v konfiguraci, ne odvozenou. Nastavte ji na přesnou doménu adminu, ne na
  nadřazenou domain (širší rpId znamená, že klíč jde použít i na ostatních subdomén).
  Když rpId není známé (`passkeyRpId` prázdné a z `adminHostPath` se nedá odvodit),
  kontejner se nezkompiluje a řekne proč.
- Pokud se doména mění napříč prostředími (staging, migrace), řešte to stabilním DNS
  názvem, ne širším rpId.

### 19.2 NEON konfigurace

```neon
fancyadmin:
    # ... ostatní konfigurace ...
    # Zapnutí passkeys — bez tohoto flagu je celá featura vypnutá (default: false)
    passkeyEnabled: true
    # Relying Party ID — doména; když není nastaveno, odvodí se host z adminHostPath
    passkeyRpId: admin.muj-projekt.cz
    # Relying Party name — zobrazuje se v dialogu autentikátoru; default = projectName
    passkeyRpName: Můj projekt
```

Povinné je jen `passkeyEnabled` (pro zapnutí), `passkeyRpId` a `passkeyRpName` jsou volitelné.

### 19.3 Entity — Passkey + rozšíření Identity

Entita Identity musí použít `IdentityPasskeysTrait` a implementovat `HasPasskeys`
(PasskeyService na ten interface spoléhá):

```php
// app/Model/Entities/Identity.php — přidat k existující entitě
use ADT\FancyAdmin\Model\Entities\IdentityPasskeysTrait;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;

#[ORM\Entity]
class Identity extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Identity, HasPasskeys /* , ... */
{
    use IdentityTrait;
    use IdentityPasskeysTrait;
}
```

```php
// app/Model/Entities/Passkey.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\PasskeyTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Passkey extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Passkey
{
    use PasskeyTrait;
}
```

**PasskeyTrait poskytuje:**

| Sloupec | Typ | Popis |
|---|---|---|
| `identity` | Identity (FK, ON DELETE CASCADE) | Vlastník klíče |
| `name` | VARCHAR(64) | Uživatelský název klíče |
| `credentialId` | VARBINARY(255), unique | Raw binary credential ID |
| `publicKey` | TEXT | Veřejný klíč (PEM) |
| `signCount` | INT UNSIGNED | Signature counter (detekce klonu) |
| `aaguid` | BINARY(16), nullable | AAGUID autentikátoru |
| `transports` | JSON, nullable | Transports z prohlížeče |
| `backupEligible` / `backupState` | BOOL, nullable | Backup flags (synchronizovaný klíč) |
| `createdAt` | DATETIME | Vytvořeno |
| `lastUsedAt` | DATETIME, nullable | Poslední přihlášení klíčem |

`IdentityPasskeysTrait` přidává do tabulky `identity` nullable sloupec `passkey_user_handle`
(BINARY(32)) — náhodný opaque WebAuthn user handle, generovaný při registraci prvního klíče
(autentikátoru se nikdy neposílá interní ID identity) — a inverzní vazbu `getPasskeys()`.

### 19.4 Query + factory

```php
// app/Model/Queries/PasskeyQuery.php
<?php

declare(strict_types=1);

namespace App\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\PasskeyQueryTrait;
use App\Model\Entities\Passkey;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends Base\BaseQuery<Passkey>
 */
class PasskeyQuery extends Base\BaseQuery implements \ADT\FancyAdmin\Model\Queries\PasskeyQuery
{
    use PasskeyQueryTrait;

    protected function applySecurityFilter(): void {}
    protected function applyAccountFilter(QueryBuilder $qb, Account $account): void {}
}
```

```php
// app/Model/Queries/Factories/PasskeyQueryFactory.php
<?php

namespace App\Model\Queries\Factories;

use App\Model\Queries\PasskeyQuery;

interface PasskeyQueryFactory extends \ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory
{
    public function create(): PasskeyQuery;
}
```

### 19.5 Form + grid (Account stránka)

```php
// app/UI/Portal/Components/Forms/Passkey/PasskeyForm.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\Passkey;

use ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyFormTrait;
use App\UI\Portal\Components\Forms\Base\BaseForm;

class PasskeyForm extends BaseForm implements \ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyForm
{
    use PasskeyFormTrait;
}
```

```php
// app/UI/Portal/Components/Forms/Passkey/PasskeyFormFactory.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\Passkey;

interface PasskeyFormFactory extends \ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyFormFactory
{
    public function create(): PasskeyForm;
}
```

```php
// app/UI/Portal/Components/Grids/Passkey/PasskeyGrid.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Grids\Passkey;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGridTrait;
use App\UI\Portal\Components\Grids\Base\BaseGrid;

class PasskeyGrid extends BaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGrid
{
    use PasskeyGridTrait {
        initGrid as initGridTrait;
    }

    public function initGrid(DataGrid $grid): void
    {
        parent::initGrid($grid);
        $this->initGridTrait($grid);
    }
}
```

```php
// app/UI/Portal/Components/Grids/Passkey/PasskeyGridFactory.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Grids\Passkey;

interface PasskeyGridFactory extends \ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGridFactory
{
    public function create(): PasskeyGrid;
}
```

Factory interfaces se registrují automaticky přes stávající `search` sekce v neonu
(`*Factory.php` v `Model/Queries` a `UI/Portal/Components`).

### 19.6 Migrace

Knihovna **žádnou migraci nedodává** — schéma vlastní projekt:

```bash
php bin/console migrations:diff
php bin/console migrations:migrate
```

Vytvoří tabulku `passkey` a přidá sloupec `identity.passkey_user_handle`.

### 19.7 Jak to funguje (bezpečnostní poznámky)

- Attestation format `none` (standard pro passkeys), `residentKey: required`
  (discoverable credentials), `userVerification: required`
- Login je usernameless — prázdné `allowCredentials`, klíč se hledá podle credential ID
  z assertion (credential-first lookup); `userHandle` se ověřuje proti
  `identity.passkey_user_handle` přes `hash_equals()`
- Challenge se drží v Nette session, one-shot (po přečtení se maže), expirace 5 minut,
  oddělené klíče pro registraci a login
- Všechny binárky v JSON jsou base64url (`PublicKeyCredential.toJSON()` formát)
- Signature counter se ověřuje (`lbuchs/webauthn` vyhodí chybu při poklesu — možný klon klíče)
- Neaktivní identita a SSO identita se klíčem nepřihlásí; po loginu platí stejný ACL check
  jako u hesla (customer/backoffice resource)
- Ceremony se spouští jen kliknutím na tlačítko — na server nejde žádný request, dokud
  uživatel neklikne, takže anonymní návštěvník login stránky nedostane session cookie
  (challenge se do session zapisuje až v okamžiku ceremony)

---

## 20. API klíče (volitelné)

Správa API klíčů pro server-to-server přístup do aplikace. Featura je **opt-in** — pokud
projekt glue třídy nevytvoří, nic se nikde nezobrazuje a v databázi žádná tabulka nevzniká;
fancyadmin sám na `ApiKey` nikde nespoléhá.

Co uživatel dostane: stránku s gridem klíčů (název, otisk klíče, účet) a side panelem pro
vytvoření a editaci. Klíč se generuje při vytvoření záznamu (32 alfanumerických znaků) a
zobrazí se **jednou** ve flash zprávě — v databázi je uložený jen jeho SHA-256 otisk
(sloupec `key`), takže z databáze klíč zpětně nezískáte. Editací názvu se klíč nemění,
kompromitovaný klíč se řeší smazáním a vytvořením nového.

### 20.1 Entita

```php
// app/Model/Entities/ApiKey.php
<?php

declare(strict_types=1);

namespace App\Model\Entities;

use ADT\FancyAdmin\Model\Entities\ApiKeyTrait;
use App\Model\Entities\Abstract\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ApiKey extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\ApiKey
{
    use ApiKeyTrait;
}
```

**ApiKeyTrait poskytuje:**

| Sloupec | Typ | Popis |
|---|---|---|
| `name` | VARCHAR(255) | Název klíče |
| `key` | VARCHAR(255), unique, nullable | SHA-256 otisk klíče |
| `account` | Account (FK, nullable) | Účet, kterému klíč patří (null = globální klíč) |

Projekt si může přidat vlastní sloupce a traity (`IsActive`, `CreatedAt`, `CreatedBy`, …)
— trait mapuje jen ta tři pole. Díky poli `account` platí obvyklá pravidla fancyadminu:
`AccountFieldListener` doplní při persistu vybraný účet a `applySecurityFilter` /
`applyAccountFilter` omezí grid na účty přihlášené identity.

### 20.2 Query + factory

```php
// app/Model/Queries/ApiKeyQuery.php
<?php

declare(strict_types=1);

namespace App\Model\Queries;

use ADT\FancyAdmin\Model\Queries\ApiKeyQueryTrait;
use App\Model\Entities\ApiKey;
use App\Model\Queries\Filters\DefaultFilters;

/**
 * @extends Abstract\BaseQuery<ApiKey>
 */
class ApiKeyQuery extends Abstract\BaseQuery implements \ADT\FancyAdmin\Model\Queries\ApiKeyQuery
{
    use DefaultFilters;
    use ApiKeyQueryTrait;

    protected function getPrimaryEntityAlias(): ?string
    {
        return 'e';
    }
}
```

```php
// app/Model/Queries/Factories/ApiKeyQueryFactory.php
<?php

namespace App\Model\Queries\Factories;

use App\Model\Queries\ApiKeyQuery;

interface ApiKeyQueryFactory extends \ADT\FancyAdmin\Model\Queries\Factories\ApiKeyQueryFactory
{
    public function create(): ApiKeyQuery;
}
```

### 20.3 Form + grid

```php
// app/UI/Portal/Components/Forms/ApiKey/ApiKeyForm.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\ApiKey;

use ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyFormTrait;
use App\UI\Portal\Components\Forms\Base\BaseForm;

class ApiKeyForm extends BaseForm implements \ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyForm
{
    use ApiKeyFormTrait;
}
```

```php
// app/UI/Portal/Components/Forms/ApiKey/ApiKeyFormFactory.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\ApiKey;

interface ApiKeyFormFactory extends \ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyFormFactory
{
    public function create(): ApiKeyForm;
}
```

```php
// app/UI/Portal/Components/Grids/ApiKey/ApiKeyGrid.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Grids\ApiKey;

use ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGridTrait;
use App\UI\Portal\Components\Grids\Base\BaseGrid;

class ApiKeyGrid extends BaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGrid
{
    use ApiKeyGridTrait;
}
```

```php
// app/UI/Portal/Components/Grids/ApiKey/ApiKeyGridFactory.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Grids\ApiKey;

interface ApiKeyGridFactory extends \ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGridFactory
{
    public function create(): ApiKeyGrid;
}
```

Sloupec s účtem se v gridu zobrazuje jen identitám s právem na `fullData` resource,
ostatní vidí jen klíče svého účtu.

Když má projekt na entitě vlastní sloupce, přepíše `initForm()` a zavolá
`addApiKeyFields()` (pole klíče bez submitu), aby submit zůstal poslední:

```php
public function initForm(Form $form): void
{
    $this->addApiKeyFields($form);

    $form->addCheckbox('isAdmin', 'app.forms.apiKey.labels.isAdmin');

    $form->addSubmit('submit', 'app.forms.apiKey.labels.submit');
}
```

Grid se rozšiřuje obvyklým aliasem traitu (`ApiKeyGridTrait::initGrid as traitInitGrid`).

### 20.4 Presenter

```php
// app/UI/Portal/Backoffice/Presenters/ApiKeys/ApiKeysPresenter.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Backoffice\Presenters\ApiKeys;

use ADT\FancyAdmin\UI\Presenters\ApiKeys\ApiKeysPresenterTrait;
use App\UI\Portal\Presenters\AuthPresenter;

class ApiKeysPresenter extends AuthPresenter
{
    use ApiKeysPresenterTrait;
}
```

Stejný presenter lze vytvořit i v zákaznické části (`Customer`), pak si každý účet spravuje
vlastní klíče. Nezapomeňte na ACL resource (`portalBackoffice.apiKeys`, resp.
`portalCustomer.apiKeys`) a položku v `NavbarMenuFactory`.

### 20.5 Migrace

Knihovna žádnou migraci nedodává — schéma vlastní projekt:

```bash
php bin/console migrations:diff
php bin/console migrations:migrate
```

Vytvoří tabulku `api_key`.

### 20.6 Ověření klíče

Klíč přijatý v požadavku se ověřuje přes query object, hashování řeší `ApiKeyQueryTrait`:

```php
$apiKey = $this->apiKeyQueryFactory->create()
    ->disableSecurityFilter()
    ->disableAccountFilter()
    ->byRawKey($rawKeyZHlavicky)
    ->fetchOneOrNull();
```

Hash se dá spočítat i přímo — `ADT\FancyAdmin\Model\Security\ApiKeyHasher::hash($rawKey)`,
generování nového klíče `ApiKeyHasher::generateRawKey()`.

Pokud projekt migruje ze starších klíčů uložených jiným způsobem (např. `password_hash`
ve vlastním sloupci `hash`), může si sloupec `hash` v entitě nechat a při prvním úspěšném
ověření dopsat do `key` hodnotu `ApiKeyHasher::hash($rawKey)` — od té chvíle stačí
`byRawKey()` a starý sloupec lze časem zrušit.

---

## Shrnutí

| Krok | Co | Proč |
|---|---|---|
| BaseEntity | Abstraktní třída s Identifier trait | Sdílený základ pro všechny entity |
| 9 entit | Identity, Account, Profile, AclRole, AclResource, Acl, Configuration, File, GridFilter | Fancyadmin vyžaduje všechny pro funkční ACL, auth, grid filtry, konfiguraci |
| AclResourceNameEnum | Enum implementující Nette\Security\Resource | Definice ACL resources pro fancyadmin config |
| BaseQuery + 6 Query tříd | QueryObject pattern s fancyadmin traits | Fancyadmin interně používá query factories pro přístup k datům |
| 6 QueryFactory interfaces | Rozšiřují fancyadmin factory interfaces | DI autowiring pro query třídy |
| Authenticator | Rozšiřuje OnetimeTokenAuthenticator | Autentizace přes Doctrine (email + heslo, OTP) |
| SecurityUser | Rozšiřuje ADT\DoctrineAuthenticator\SecurityUser | Session management, isAllowed(), isAdmin() |
| Permission | Rozšiřuje fancyadmin Permission | ACL authorizátor |
| EntityManager | Rozšiřuje ADT\DoctrineComponents\EntityManager | Rozšířený EntityManager s helper metodami |
| 3 Listeners | CreatedBy, AccountField, SelectAccount | Automatické nastavování created_by, account polí při persistu |
| Translator | Rozšiřuje Contributte\Translation\Translator | Překlady |
| RouterFactory | Integruje FancyAdminRouter | Sign routes, portal routes |
| Portal presentery | BasePresenter + AuthPresenter s fancyadmin traits | Admin layout, auth check, side panel |
| Passkey glue třídy | Entita Passkey, PasskeyQuery + factory, PasskeyForm + factory, PasskeyGrid + factory | Přihlašování přes passkeys (WebAuthn) — viz sekce 19 |
| ApiKey glue třídy | Entita ApiKey, ApiKeyQuery + factory, ApiKeyForm + factory, ApiKeyGrid + factory, ApiKeysPresenter | Správa API klíčů pro server-to-server přístup — viz sekce 20 |
