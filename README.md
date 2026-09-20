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
- Sloupce: `firstName`, `lastName`, `email`, `username`, `password`, `phoneNumber`, `context`, `isActive`, `ssoSub`
- Timestamps: `createdAt`, `updatedAt`, `createdBy`, `updatedBy`
- Vztahy: `profiles` (1:N), `roles` (M:N s AclRole), `selectedAccount` (N:1), `sso` (N:1)

> `ssoSub` drží claim `sub` z SSO, tedy stabilní identifikátor uživatele u poskytovatele
> identity. Používá se k párování při přihlášení — proti e-mailu je odolnější, protože
> e-mail je měnitelný na obou stranách. Sloupec je nullable (uživatel nemusí chodit přes
> SSO) a unikátní. Po přidání entity spusťte migraci.
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

**AclRoleTrait poskytuje:** `name`, `type` (AclRoleTypeEnum), `context`, `isAdmin`, `needsSso` (vynucené SSO, sekce 18), `needs2fa` (vynucené přihlášení klíčem, sekce 19.8), `acls` (1:N), metody `isAllowed()`, `getResources()`, `getRoleId()`

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

Definujte enum s ACL resources. Fancyadmin vyžaduje minimálně 4:
- customer resource (přístup do zákaznické části)
- backoffice resource (přístup do administrace)
- full data resource (plný přístup k datům)
- personal data resource (změna vlastních osobních údajů na stránce „Můj profil", viz
  sekce 14 „ProfilePresenter")

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
	case PERSONAL_DATA = 'portal.personalData';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
```

Fancyadmin má svůj vlastní default (`ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum::PROFILE_PERSONAL_DATA`,
`'profile.personalData'`) — pokud projekt používá vlastní enum (jako výše), nakonfigurujte
resource výslovně v `common.neon`, stejně jako u `customerAclResource` / `backofficeAclResource`
/ `fullDataAclResource` (viz sekce 15):

```neon
fancyadmin:
	personalDataAclResource: App\Model\Entities\Enums\AclResourceNameEnum::PERSONAL_DATA
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

### ProfilePresenter (stránka „Můj profil")

Osobní údaje přihlášeného uživatele, změna hesla, přihlášená zařízení a karta
přihlašovacích klíčů. Presenter **musí existovat v obou portálových modulech**
(`PortalBackoffice` i `PortalCustomer`) — fancyadmin na něj přesměrovává modulově
relativně (`Profile:default`, viz 19.8 a 19.9), takže uživatel zamčený v customer modulu
se na backoffice presenter nedostane.

```php
// app/UI/Portal/Backoffice/Presenters/Profile/ProfilePresenter.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Backoffice\Presenters\Profile;

use ADT\FancyAdmin\UI\Presenters\Profile\ProfilePresenterTrait;
use App\UI\Portal\Presenters\AuthPresenter;

class ProfilePresenter extends AuthPresenter
{
	use ProfilePresenterTrait;
}
```

V `PortalCustomer` pak stojí vedle sebe `Profile` (moje údaje) a `Profiles` (seznam
profilů účtu) — jsou to dva různé presentery.

#### ACL na Profilu: presenter je vyjmutý, změna osobních údajů ne

`AuthPresenterTrait::validatePresenterPermission()` vyjímá `Profile` presenter z běžné
presenter-level ACL kontroly úplně (`isProfilePresenter()` → `return;`). Je to záměr, ne
díra: presenter vždy pracuje jen s daty přihlášeného uživatele a bez výjimky by se neadmin
zamknutý na povinné registraci passkey (viz 19.8, `enforcePasskeyLogin()`) dostal do 403
místo na formulář, kde si klíč zaregistruje. Ze stejného důvodu zůstávají bez ACL i změna
hesla, správa passkeys, grid přihlášených zařízení a `handleLogoutAll()`.

Změna osobních údajů (`firstName`/`lastName`/`phoneNumber`, `PersonalDataFormTrait`) je ale
jediná akce na Profilu, kterou chcete moci projektově omezit (typicky: údaje spravuje jen
account manager, běžný uživatel je jen čte). Ta je proto pod samostatným ACL resourcem
`AclResourceNameEnum::PROFILE_PERSONAL_DATA` (`profile.personalData`), kontrolovaným
explicitně (`SecurityUserTrait::isAllowedPersonalData()`) na **obou** vstupech nezávisle na
presenter-level výjimce:
- `ProfilePresenterTrait::handleEditPersonalData()` — otevření formuláře,
- `ProfilePresenterTrait::createComponentPersonalDataSidePanel()` — odeslání formuláře míří
  signálem přímo na komponentu a `handleEditPersonalData()` obchází, takže bez kontroly i tady
  by šel zámek na signálu obejít.

Šablona `default.latte` navíc tlačítko „Upravit osobní údaje“ skrývá přes
`$canEditPersonalData` (nastavuje `actionDefault()`), aby ho neviděl uživatel, který na něj
stejně nemá právo.

Resource je konfigurovatelný stejným způsobem jako `fullDataAclResource` (`personalDataAclResource`
v `common.neon`, viz sekce 4 a 15) a čerstvá instalace bez proběhlé migrace balíčku nepadá —
`isAllowedPersonalData()` neznámý resource v authorizátoru bere jako "zatím nic neomezuje", ne
jako zákaz (viz sekce 16).

> **BC break (přejmenování z `Account`).** Presenter „Můj účet" se jmenoval `Account`
> a kolidoval s entitou `Account` (tenant) i s presenterem `Accounts` (seznam tenantů).
> Při aktualizaci balíčku je potřeba v projektu:
> - přejmenovat `AccountPresenter` → `ProfilePresenter` v obou modulech
>   (`ADT\FancyAdmin\UI\Presenters\Account\AccountPresenterTrait` →
>   `ADT\FancyAdmin\UI\Presenters\Profile\ProfilePresenterTrait`),
> - přepsat vlastní odkazy `Account:default` → `Profile:default`,
> - přejmenovat `UserMenu::setAddMyAccountMenuItem()` → `setAddMyProfileMenuItem()`
>   a `isAddMyAccountMenuItem()` → `isAddMyProfileMenuItem()`,
> - u vlastních překladů přejmenovat `presenters.account.*` (klíče stránky),
>   `passkeys.account.*` → `passkeys.profile.*` a
>   `modules.web.navbar.account.myAccount` → `modules.web.navbar.profile.myProfile`.
>
> Entita `Account`, `AccountQuery`, `AccountGrid`, `AccountForm`, presenter `Accounts`
> ani routovací parametr `selectedAccount` se nemění. URL se mění z `/<id>/account`
> na `/<id>/profile`; migrace nejsou potřeba.

### Kam se uživatel vrátí po přihlášení

Nepřihlášený požadavek na AuthPresenter skončí na přihlašovací stránce a cíl se zapamatuje
do cookie `returnPath` (host-only, HttpOnly, SameSite=Lax, 10 minut) — **bez ohledu na
HTTP metodu**.

Platí tedy invariant, že **nepřihlášený požadavek nikdy nesáhne na session**. Nette
backlink (`Presenter::storeRequest()`) by ji naopak založil, takže by šlo `session_storage`
nafouknout requesty zvenčí: nejde ani o POST, na obejití by stačil GET s hlavičkou
`X-Requested-With: XMLHttpRequest`.

Cenou je, že se **POST po přihlášení nezopakuje** — uživatel skončí na cílové stránce
a formulář odešle znovu. Zopakování POSTu ale stejně z velké části nefungovalo: CSRF token
je `token ^ session ID`, takže když je uživatel nepřihlášený kvůli vypršelé session, po
loginu dostane jiné session ID a replay na CSRF spadne.

`RedirectAfterLoginTrait::redirectAfterLogin()` po přihlášení zkusí nejdřív session
backlink, pak cookie, a nakonec spadne na výchozí route. Backlink fancyadmin sám nezakládá,
ale zpracovat ho umí — aplikace si `storeRequest()` může zavolat sama tam, kde opravdu
potřebuje zopakovat POST, a vzít si za to tu expozici na sebe.

Presenter, který z AuthPresenteru nedědí (typicky výdej souboru z odkazu v e-mailu),
si cíl uloží sám:

```php
use ADT\FancyAdmin\DI\Injects\ReturnPathInject;

class DownloadPresenter extends BasePresenter
{
	use ReturnPathInject;

	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->_returnPath->store($this->getHttpRequest()->getUrl());
			$this->redirect(':Portal:Sign:in');
		}
	}
}
```

Cíl v cookie **není svázaný s identitou** (na rozdíl od session backlinku), takže se na
něm nesmí stavět autorizace — cílová akce si musí právo přihlášeného uživatele ověřit sama.

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

### Aktualizace balíčku — migrace v `src/Migrations`

Balíček nese vlastní Doctrine migrace (`ADT\FancyAdmin\Migrations\*`), které se do projektu
zapojí automaticky (nettrine migrations skenuje i vendor namespace) a při `migrations:migrate`
proběhnou spolu s projektovými. Typicky přidávají jen sloupce/tabulky nebo (jako
`Version20260917100000`, ACL resource `profile.personalData`) systémová data — assignment
existujícího ACL resource všem existujícím rolím, aby se upgradem nikomu nic nezměnilo.

Co si musí projekt po `composer update adt/fancyadmin` dodělat sám:
1. `php bin/console migrations:migrate` — spustí i migrace balíčku.
2. `php bin/console fancyadmin:generate-missing-acl-resources` — dogeneruje projektovou
   migraci pro resources z **projektového** `AclResourceNameEnum` (balíčkové enum cases
   generuje `Version20260917100000` rovnou, `GenerateMissingAclResourcesCommand` navíc
   skenuje `src/Model/Entities/Enums` fancyadminu samo, takže by nemělo najít nic k doplnění
   pro nový resource — spusťte pro jistotu, hlavně kvůli vlastním presenterům/enumům).
3. Pokud projekt chce nový resource `profile.personalData` **omezit** (ne jen mít
   nainstalovaný), odebrat ho ručně u vybraných rolí v Backoffice → Role, nebo vlastní
   migrací — balíček ho z BC důvodů přiděluje všem.

Do doby, než migrace proběhne, kód nespadne: `SecurityUserTrait::isAllowedPersonalData()`
neznámý resource v authorizátoru bere jako "zatím nic neomezuje" (viz sekce 14), takže okno
mezi deployem kódu a spuštěnou migrací není výpadek.

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
    │   ├── Presenters/
    │   │   ├── AuthPresenter.php
    │   │   └── BasePresenter.php
    │   ├── Backoffice/
    │   │   └── Presenters/
    │   │       └── Profile/
    │   │           └── ProfilePresenter.php
    │   └── Customer/
    │       └── Presenters/
    │           └── Profile/
    │               └── ProfilePresenter.php
    └── Web/
        └── Presenters/
            └── BasePresenter.php
```

Presenter `Profile` („Můj profil") musí být v **obou** portálových modulech — redirecty
fancyadminu na něj jsou modulově relativní (viz sekce 14, 19.8 a 19.9).

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
| `isActive` | bool | Zapojuje se instance do přihlašování? Default `true` (v DB sloupec `is_active`) |

`baseUrl` i `hostUrl` musí být absolutní http(s) URL — formulář odmítne hodnotu bez schématu.
Kam smí mířit se needituje, to je věc administrátora.

#### Proč `isActive`

Silent SSO na přihlašovací stránce projde **všechny aktivní** instance a na každou udělá
jeden `prompt=none` check. Vadná konfigurace tedy ovlivní login celé platformy — deaktivace
je způsob, jak takovou instanci odstavit, aniž by se musela smazat: konfigurace i identity
na ni navázané zůstanou zachované. Smazat ji ostatně nejde, dokud na ní visí identity.

Co deaktivace ovlivní:

- **Silent SSO** instanci vynechá, `prompt=none` check se na ni neposílá.
- **SSO uživatelé navázaní na instanci** (identita se `sso_id` a alespoň jednou rolí
  s `needs_sso`) se chovají jako **běžní uživatelé s heslem**: přihlásí se lokálním heslem,
  lost password i reset hesla z gridu uživatelů pošlou lokální recovery mail a na Profilu
  si mění lokální heslo. `KeycloakManager::getInstanceForIdentity()` pro ně vrátí `null`
  a všechny tyhle cesty propadnou na stejnou větev, kterou používají uživatelé bez SSO.
- **Callback rozpracovaného requestu, odhlášení a backchannel logout fungují dál.** Odhlášení
  si instanci bere přes `getInstanceForIdentity($identity, activeOnly: false)`, takže uživatel
  přihlášený před deaktivací se odhlásí i z Keycloaku a deaktivace ho neuvězní.

Fallback na heslo je záměr: deaktivace je zároveň nouzový režim, kdy se SSO uživatelé
dostanou do aplikace i při výpadku nebo špatné konfiguraci Keycloaku. Kdo lokální heslo
nemá, nastaví si ho přes lost password. Po reaktivaci instance se přihlašování zase řídí
Keycloakem a lokální heslo se ignoruje.

#### Vyzkoušení instance před aktivací

Akce **Vyzkoušet** v SSO gridu ověří konfiguraci, aniž by se instance musela aktivovat.
Běží ve dvou vrstvách, protože každá chytá jinou třídu chyb:

1. **Serverová sonda**: `client_credentials` grant na `baseUrl`. Ověří interní URL, realm,
   Client ID i Client Secret. Nepotřebuje prohlížeč, takže když selže, končí se hned a do
   gridu se rovnou zapíše chyba.
2. **Zkušební průchod**: admin projde reálným silent checkem (`prompt=none`) na `hostUrl`
   přes existující `silent-check` redirect URI. Tenhle krok ověří jen ty případy, kdy
   Keycloak **přesměruje zpět** do aplikace:
   - `code` v odpovědi (admin má v Keycloaku session) i `error=login_required`
     (resp. `interaction_required`) jsou **úspěch**: Keycloak request přijal a zpracoval,
     jen zrovna neběží žádná SSO session. U admina, který v Keycloaku přihlášený není, je to
     očekávaný výsledek.
   - Jiná OAuth chyba (např. `unauthorized_client`, `invalid_scope`) se zobrazí jako
     chybová hláška s kódem chyby. Kód se před zobrazením sanitizuje na `[a-z0-9_.-]`,
     max 64 znaků, aby se do hlášky nedostalo nic, co do parametru vloží Keycloak nebo někdo
     za něj.

Co zkušební průchod **neověří** hláškou: když Keycloak `client_id` nezná, `redirect_uri`
nemá registrované, nebo `hostUrl` není z prohlížeče dosažitelná, Keycloak zpět nepřesměruje.
Admin skončí na chybové stránce Keycloaku (typicky `Invalid parameter: redirect_uri`,
`Client not found`), resp. na síťové chybě prohlížeče, a do aplikace se nevrátí. Právě to,
že se nevrátil do gridu, je v těchto případech výsledek testu.

Výsledek se do gridu dostane přes návratovou URL: `actionSilentCheck` k `backRedirect` ze
session přidá query parametr `ssoTest` (konstanty `Keycloak::SSO_TEST_PARAM`,
`Keycloak::SSO_TEST_OK`, hodnota `ok` nebo kód chyby) a `SsoPresenterTrait::actionDefault`
z něj udělá flash zprávu a URL redirectem vyčistí, aby se hláška při refreshi neopakovala.
Flash zpráva se nepoužívá přímo, protože redirect na absolutní URL by ji do cílové stránky
nepřenesl.

Průchod **nikoho nepřihlásí**: příznak `isTest` se drží v session u jednorázového `state`
(ne v URL, aby nešel podvrhnout) a `actionSilentCheck` podle něj místo autentizace jen
ohlásí výsledek. Přijatý `code` se za token nevymění. Bez toho by se admin mohl přihlásit
cizí identitou, případně by se přes `defaultRole` provisionovala nová.

Používá se existující `silent-check` redirect URI, takže se v Keycloaku **nic nepřidává**.

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

   | id | name | realm | baseUrl | hostUrl | clientId | clientSecret | frontendClientId | default_role_id | is_active |
   |---|---|---|---|---|---|---|---|---|---|
   | 1 | hlavni | muj-realm | http://keycloak:8080 | https://auth.example.cz | app-client | secret123 | app-public | 5 | 1 |

   Kde `default_role_id` je cizí klíč na `acl_role.id` — ID role, která se automaticky přiřadí novému uživateli při prvním SSO přihlášení. Pokud nechcete automatické přiřazení role, nechte `NULL`.

   `is_active` (`TINYINT(1) NOT NULL DEFAULT 1`) říká, zda se instance zapojuje do přihlašování (viz 18.2, „Proč `isActive`"). Novou instanci lze založit s `0`, vyzkoušet ji akcí **Vyzkoušet** a aktivovat až potom.

   **Migrace:** knihovna migraci pro tabulku `sso` nepřináší (v `src/Migrations/` je jen nesouvisející migrace), sloupce vznikají z `SsoTrait` v entitě projektu. Sloupec `is_active` si proto projekt musí do existující tabulky přidat sám, buď vygenerováním migrace z entity, nebo ručně:

   ```sql
   ALTER TABLE sso ADD is_active TINYINT(1) DEFAULT 1 NOT NULL;
   ```

2. **Označte role, které vyžadují SSO**: v tabulce `acl_role` nastavte `needs_sso = 1` u rolí, jejichž uživatelé se mají přihlašovat výhradně přes Keycloak. Sloupec `acl_role.sso_id` neexistuje, role sama na konkrétní instanci navázaná není.

3. **Navažte identity na instanci**: v tabulce `identity` nastavte `sso_id` na `sso.id`. Identita se přihlašuje přes SSO, když má `identity.sso_id` **a zároveň** alespoň jednu roli s `needs_sso = 1` Samotná vazba `sso_id` bez takové role SSO nevynutí a samotná role bez `sso_id` neříká, přes kterou instanci se přihlašovat.

Při zadání emailu na login stránce fancyadmin zjistí SSO instanci z identity (`identity.sso_id` + role s `needs_sso`) a přesměruje na odpovídající Keycloak. Pokud identita takovou vazbu nemá, zobrazí se standardní přihlášení heslem. Pokud vazbu má, ale instance je deaktivovaná, heslem se nepřihlásí a dostane hlášku o dočasné nedostupnosti SSO (viz 18.2).

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

// Získat instanci podle identity (identity.sso_id + role s needs_sso; deaktivovaná instance vrátí null)
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
2. **DB** — u identit nastavte `identity.sso_id` na novou instanci a u jejich rolí `acl_role.needs_sso = 1`
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
nevykresluje tlačítko na login stránce ani karta na stránce Profil a všechny passkey operace
jsou zablokované i server-side (`PasskeyService::assertEnabled()`). Existující klíče
v DB při vypnutí zůstávají — po opětovném zapnutí zase fungují. Ve výchozím stavu je passkey
alternativa k heslu; rolí s flagem `needs2fa` se ale dá přihlášení klíčem **vynutit** a heslo
takové identitě přestane fungovat (viz 19.8). Klíč si může zaregistrovat i identita
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
- **Profil** — karta „Přihlašovací klíče": přidání klíče (side panel s povinným názvem),
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
    # Záchranná cesta při vynuceném 2FA: jednorázový kód na e-mail (default: true, viz 19.9)
    passkeyEmailOtpEnabled: true
    # Zamknout uživatele přihlášeného kódem na Profil, dokud si nepřidá klíč (default: false)
    passkeyEnrollmentRequired: false
```

Povinné je jen `passkeyEnabled` (pro zapnutí), zbytek je volitelný.
`passkeyEmailOtpEnabled` i `passkeyEnrollmentRequired` jsou aktivní jen při
`passkeyEnabled: true`; při vypnutém `passkeyEmailOtpEnabled` platí tvrdá zeď popsaná v 19.8.

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

> Kolekci `passkeys` si nemapujte ručně. `PasskeyTrait` ji sice vyžaduje kvůli `inversedBy`,
> ale ruční kolekce projde i `orm:validate-schema` — a protože entitě pak chybí
> `HasPasskeys` (a s ním `passkeyUserHandle`), registrace klíče spadne za běhu na 500.
> Při `passkeyEnabled: true` to hlídá `FancyAdminExtension` už při kompilaci kontejneru.

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

### 19.5 Form + grid (stránka Profil)

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

### 19.8 Vynucení přihlášení klíčem (2FA)

ACL role má flag **`needs2fa`** („Vyžaduje 2FA" ve formuláři role). Identita, která má
alespoň jednu roli s tímto flagem, se smí přihlásit **výhradně přihlašovacím klíčem** —
heslo jí login formulář odmítne s hláškou `fcadmin.passkeys.errors.passwordDisabled`
(kontrola běží až za `authenticate()`, aby se hláška nedala použít na enumeraci účtů).
Při zapnutém `passkeyEmailOtpEnabled` (default) se místo té hlášky nabídne druhý krok
s jednorázovým kódem na e-mail — viz 19.9.

Požadavek se vyhodnocuje přes **všechny role identity**: její vlastní i role všech jejích
profilů. Uživatel s více profily tedy 2FA neobejde přepnutím účtu.

**Precedence SSO > 2FA > heslo.** Identita s rolí `needsSso` a přiřazenou (aktivní) SSO
instancí se dál řeší Keycloakem a `needs2fa` se u ní ignoruje — autoritou je poskytovatel
identity, kde se druhý faktor nastavuje. Jakmile SSO odpadne (zrušená vazba nebo
deaktivovaná instance), `needs2fa` se aktivuje.

**Bootstrap okno.** Dokud uživatel žádný klíč nemá, dostane se dovnitř i bez něj — jinak by
se k registraci prvního klíče nedostal. Při zapnutém `passkeyEmailOtpEnabled` (default) po
něm systém kromě hesla chce i jednorázový kód z e-mailu (19.9); při vypnutém mu stačí heslo.
Aplikace ho pak pustí jen na stránku **Profil**:
`AuthPresenterTrait::startup()` ho odjinud přesměruje na `Profile:default` s hláškou
`fcadmin.passkeys.messages.enrollmentRequired` a stránka mu nad kartou s klíči vysvětlí,
proč je tam zamčený — pokud je zapnuté `passkeyEnrollmentRequired` (19.9), jinak se dostane
kamkoliv. Po registraci prvního klíče je heslo pro něj mrtvé. Při vypnutém
`passkeyEmailOtpEnabled` je to **vědomé omezení**: v bootstrap okně pak stojí bezpečnost
účtu jen na heslu, takže flag zapínejte společně s rozumnou politikou hesel a u existujících
uživatelů ideálně až po tom, co si klíč zaregistrují.

**Stránka Profil je vyjmutá z generického presenter ACL checku**
(`AuthPresenterTrait::validatePresenterPermission()`), protože ukazuje vždy jen data
přihlášeného uživatele — a hlavně proto, že resource `portalBackoffice.profile` /
`portalCustomer.profile` projekty typicky nemají a neadmin by místo registrace klíče
skončil ve 403. Presenter `Profile` proto **musí existovat v obou modulech**
(`PortalBackoffice` i `PortalCustomer`) — redirect v `enforcePasskeyLogin()` je modulově
relativní, takže uživatel zamčený v customer modulu se na backoffice presenter nedostane.
V `PortalCustomer` tak stojí vedle sebe `Profile` (moje údaje přihlášeného uživatele)
a `Profiles` (seznam profilů účtu); jsou to dva různé presentery a je to v pořádku.

**Precedence kontrol při každém requestu.** `AuthPresenterTrait::enforcePasskeyLogin()` je
vyhodnocuje v tomhle pořadí a na pořadí záleží:

1. session vzniklá jednorázovým kódem (`otpSession`, 19.9) — druhým faktorem prošla, takže
   se nechává naživu; na `Profile` ji drží jen `passkeyEnrollmentRequired`,
2. stará heslová session identity, která klíč už má — odhlásí,
3. bootstrap okno (identita klíč vyžaduje, ale žádný nemá) — pustí jen na `Profile`.

Bez první položky by session z jednorázového kódu spadla do druhé kontroly (je to session
bez passkey markeru) a uživatele by rovnou odhlásila — proto se z první větve vrací vždycky,
bez ohledu na `passkeyEnrollmentRequired`.

**Už přihlášená heslová session.** Session se při přihlášení klíčem (a po registraci klíče
v bootstrap okně) označí server-side markerem. Identita, která klíč vyžaduje a už ho má,
se v session bez markeru odhlásí s hláškou
`fcadmin.passkeys.errors.passwordSessionRevoked` — zapnutí flagu tedy zneplatní i běžící
heslové session. Stejně skončí i každá jiná cesta k přihlášení, která není klíč: obnova
hesla přes e-mail i jednorázový přihlašovací odkaz (`?token=`, tedy i „Přihlásit se jako"
u takové identity) končí odhlášením, jakmile identita klíč má. Je to záměr — jinak by se
klíč dal obejít.

**Poslední klíč nelze smazat.** Identita s `needs2fa` by se tím downgradovala na heslo,
takže smazání posledního klíče odmítne `PasskeyGridTrait::handleDeletePasskey()`
(`fcadmin.passkeys.errors.lastKeyRequired`) — tlačítko je navíc v gridu skryté.

**Při `passkeyEnabled: false` je flag zcela inertní** — nikoho neblokuje (jinak by jeden
config vyřadil všechny adminy) a checkbox se ve formuláři role ani nezobrazí (stejně jako
`needsSso` při vypnutém Keycloaku).

Sloupec `acl_role.needs2fa` (NOT NULL, default 0) vlastní jako celé schéma projekt, takže
po aktualizaci balíčku spusťte:

```bash
php bin/console migrations:diff
php bin/console migrations:migrate
```

### 19.9 Přihlášení jednorázovým kódem (fallback)

Samotné 19.8 má nepříjemný důsledek: uživatel s `needs2fa`, který si sedne k novému
zařízení bez klíče (nový notebook, rozbitý telefon, klíč nesynchronizovaný přes správce
hesel), se do aplikace nedostane vůbec a musí volat adminovi. Fallback to řeší tak, že ho
pustí dovnitř a zároveň ho donutí si tam klíč přidat.

**Kdy se druhý krok zobrazí.** Jen když je současně splněné všechno z toho:
`passkeyEnabled: true`, `passkeyEmailOtpEnabled: true` (default), identita má roli
s `needs2fa`, není to uživatel s povinným SSO loginem (`isPasskeyRequired()` u něj vrací
`false`) a **právě zadal správné heslo**. Místo hlášky `passwordDisabled` ho login formulář
přesměruje na `:Portal:Sign:twoFactor` (`/sign/two-factor`). Ten má dvě obrazovky:

**Výběr způsobu ověření**

1. tlačítko **Přihlásit se přihlašovacím klíčem** (stejná WebAuthn ceremony jako na login
   stránce) — jen pokud identita nějaký klíč má,
2. tlačítko **Přihlásit se jednorázovým heslem z e-mailu** — odešle kód a překlopí na druhou
   obrazovku.

**Zadání kódu**

Uživateli se ukáže, na jakou adresu kód odešel, pod tím odkaz na opětovné odeslání (aktivní
až po minutě, do té doby se místo něj zobrazuje zbývající čas), pole pro kód a přihlašovací
tlačítko. Odkazem dole se dá vrátit zpátky na výběr způsobu; už odeslaný kód zůstává platný.
Kód má 6 znaků, platí 10 minut a je jednorázový.

Stav „kód odeslán" drží timestamp v session, který zároveň hlídá prodlevu mezi odesláními.
Zaniká spolu s čekajícím stavem (10 minut) nebo návratem na výběr metody.

**Platí i pro identitu, která ještě žádný klíč nemá.** Bootstrap okno z 19.8 tím nezaniká,
ale dostane se do něj až přes heslo **i** kód z e-mailu. Je to silnější než chování bez
fallbacku, kde takovému uživateli stačilo samotné heslo.

**Zámek na Profilu (`passkeyEnrollmentRequired`, default `false`).** Session z jednorázového
kódu se označí markerem `otpSession`. Při `passkeyEnrollmentRequired: true` je uživatel
přihlášený, ale **zamčený na stránce Profil** — odjinud ho `enforcePasskeyLogin()` vrátí zpět
s hláškou a nad kartou klíčů se mu vysvětlí proč; zámek povolí až registrace klíče
(`handlePasskeyRegisterVerify()` marker zahodí a session označí jako passkey session).
Ve výchozím stavu (`false`) žádný zámek není a e-mailový kód je plnohodnotný druhý faktor.
Marker se nastavuje tak jako tak — bez něj by session z kódu spadla do kontroly „stará
heslová session" a uživatele s klíčem by rovnou odhlásila.

> **Poctivě: tohle 2FA oslabuje.** Kdo ovládne mailovou schránku uživatele **a zná jeho
> heslo**, dostane se dovnitř bez klíče — z „něco vím + něco mám" se stává „něco vím +
> jiné něco vím". S `passkeyEnrollmentRequired: false` (default) navíc nic uživatele netlačí
> k phishing-rezistentnímu faktoru: klíč zůstane nepovinný napořád a tenhle stav je trvalý,
> ne přechodný. Zapnutí zámku to jen zmírňuje (útočník po sobě zanechá zaregistrovaný klíč,
> který je vidět na Profilu, a oběť dostane e-mail s kódem, o který nežádala — proto v něm
> stojí výzva ke změně hesla). Pro prostředí, kde má 2FA držet i proti kompromitovanému
> mailboxu, fallback **vypněte** (`passkeyEmailOtpEnabled: false`) a řešte ztracené klíče
> procesně přes administrátora.

**Proč se kód neověřuje přes `Authenticator::authenticate()`.**
`OnetimeTokenAuthenticator::verifyCredentials()` bere druhý argument jako heslo *nebo* OTP
token — pokud heslo nesedí, zkusí ho ještě dohledat mezi jednorázovými tokeny. Kdyby druhý
krok volal `authenticate()`, stačilo by do pole pro kód napsat znovu heslo a druhý faktor
by se obešel. Kód se proto ověřuje napřímo přes `OnetimeTokenService::findToken()`
(`markAsUsed: false`) a navíc se kontroluje, že token patří právě té identitě, která na
druhý faktor čeká (`objectClass` + `objectId`). `usedAt` doplní až `onLoggedIn` hook
v `ADT\DoctrineAuthenticator\OTP\SecurityUser` — kód se tedy spotřebuje jen při skutečném
přihlášení.

**Proč se token ukládá s `identifier` (e-mailem).** `findToken()` s `identifier === null`
má v dotazu podmínku `ot.identifier IS NULL`. Token uložený s identifikátorem tudíž nejde
použít jako `?token=` v URL, což je cesta, kterou zpracovává
`AuthPresenterTrait::startup()`. Bez toho by byl každý odeslaný šestiznakový kód zároveň
brute-forcovatelným přihlašovacím odkazem.

**Další zábrany.**

- Čekající stav v session drží **jen ID identity** s expirací 10 minut a vzniká výhradně
  po úspěšném ověření hesla. Není v něm nic, co by samo o sobě k přihlášení stačilo.
- Přímý přístup na `/sign/two-factor` bez čekajícího stavu (i po jeho expiraci) končí
  redirectem na `:Portal:Sign:in`.
- Pět neúspěšně zadaných kódů čekající stav zruší a vrátí uživatele na přihlášení.
- Mezi dvěma odesláními kódu musí uběhnout minuta; dřívější pokus skončí hláškou
  `fcadmin.passkeys.errors.resendTooSoon`.
- Odesílání kódů kryje navíc limit tokenů na IP (`OnetimeTokenService`: 5 **nepoužitých**
  tokenů za 15 minut); jeho vyčerpání skončí hláškou
  `fcadmin.passkeys.errors.tooManyCodeRequests`, ne pětistovkou. Úspěšné přihlášení token
  označí za použitý, takže do limitu se počítají jen kódy, které nikdo nedotáhl — za NATem
  se sdílenou IP to ale stojí za ověření.
- Před ověřením kódu se znovu kontroluje `isPasskeyRequired()` a `isActive` — role se mezi
  zadáním hesla a kódu mohly změnit.
- Session vzniklá kódem **nedostane** passkey marker (`markPasskeySession()`), naopak se
  případný zděděný marker z dřívějšího přihlášení klíčem ve stejném prohlížeči zahodí.
- Marker `otpSession` platí jen pro tu jednu session: odhlášení session nemaže, takže každá
  přihlašovací cesta (heslo, klíč, odkaz `?token=`) na začátku volá
  `PasskeyService::clearTwoFactorSession()`. Bez toho by ho zdědil i další uživatel,
  který se ve stejném prohlížeči přihlásí po něm.

**Co musí dodat projekt.** Formulář druhého kroku (analogicky k `SignInForm` z 19.5),
jinak `Sign:twoFactor` skončí `RuntimeException` s odkazem sem:

```php
// app/UI/Portal/Components/Forms/TwoFactor/TwoFactorForm.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\TwoFactor;

use ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorFormTrait;
use App\UI\Portal\Components\Forms\Base\BaseForm;

class TwoFactorForm extends BaseForm implements \ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorForm
{
    use TwoFactorFormTrait;
}
```

```php
// app/UI/Portal/Components/Forms/TwoFactor/TwoFactorFormFactory.php
<?php

declare(strict_types=1);

namespace App\UI\Portal\Components\Forms\TwoFactor;

interface TwoFactorFormFactory extends \ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorFormFactory
{
    public function create(): TwoFactorForm;
}
```

Presenter `Profile` musí existovat v **obou** modulech (viz 19.8) — redirect na zámek je
modulově relativní. Žádná migrace potřeba není, tabulku `onetime_token` má projekt už kvůli
obnově hesla. Na straně JS taky nic: druhý krok vykresluje stejný blok `section-passkey`
s týmiž data atributy jako login stránka a komponenta `Forms/SignIn/index.js` má listener
delegovaný na `document`.

> **BC break — `Mailer`.** Interface `ADT\FancyAdmin\Model\Mailer\Mailer` má novou metodu
> `sendTwoFactorCodeMail(Identity $identity, int $tokenLifetimeMinutes): void`. Projekty
> postavené na `MailerTrait` (což je standard) nemusí dělat nic — implementaci i šablonu
> `twoFactorCode.latte` dodává trait. Projekt s vlastní implementací interface si metodu
> musí doplnit. Vlastní šablona mailu se jako u ostatních přebije souborem
> `twoFactorCode.latte` ve vlastním `templates/` adresáři.

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

## 21. Auditní stopa změn entit (volitelné)

`audit_log` je jeden append-only stream bezpečnostních událostí (přihlášení, zamítnutý
přístup, export, výdej souboru). Změny entit do něj neputují samy — `change_log`
z `adt/doctrine-loggable` je provozní historie a je hustá, auditní stopa se naproti tomu
dlouhodobě archivuje a čte ji auditor.

Navěšení stačí jedno (pořadí rozšíření zná projekt, proto se to nedělá automaticky):

```neon
doctrineLoggable:
	onLogEntry:
		- [@fancyAdmin.changeLogAuditSubscriber, logEntry]
```

Tím se rovnou auditují **entity fancyadminu** — poznají se podle rozhraní, které
implementují, takže projekt k tomu nemusí sahat do entit:

| Rozhraní | `action` |
|---|---|
| `Identity`, `Passkey`, `ApiKey`, `Sso` | `identity_change` |
| `Acl`, `AclRole`, `AclResource` | `acl_change` |
| `Account`, `Profile` | `account_change` |
| `Configuration` | `configuration_change` |

Osa je doména, ne entita: detekční pravidla se pak klíčují na jednu hodnotu místo výčtu
tříd a přibytí další entity do domény nic nerozbije.

### Entity projektu

Ty fancyadmin nezná, takže se vybírají **jmenovitě**, atributem (ten zároveň přebíjí
výchozí akci, kdyby projektu doménové dělení nesedělo):

```php
use ADT\FancyAdmin\Model\Attributes\Audited;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;
use ADT\DoctrineLoggable\Attributes as ADA;

#[ORM\Entity]
#[ADA\LoggableEntity]
#[Audited(action: 'identity_change')]
class Identity
{
	#[ORM\Column]
	#[ADA\LoggableProperty]
	#[AuditedValue]
	protected ?string $email = null;

	// bez #[AuditedValue]: v auditu zůstane jen název změněné vlastnosti
	#[ORM\Column]
	#[ADA\LoggableProperty]
	protected ?string $phoneNumber = null;
}
```

**`action`** se prvním nasazením zafixuje — `audit_log` je append-only a zpětně ji
přejmenovat nejde, aniž by starým záznamům přestal rozumět dotaz nad novými.

**`#[AuditedValue]`** rozhoduje, u kterých vlastností se přenese i stará a nová hodnota.
Auditní stopa žije déle než provozní data, takže co do ní jednou spadne, zůstane tam
i po smazání účtu — hodnoty se proto vybírají jmenovitě (role, stav účtu, příznak
zaplacení). Celý payload stejně prochází `SensitiveDataSanitizer`, tedy stejnou
sanitizací jako ostatní logy.

### Co fancyadmin označuje sám

Traity fancyadminu nesou `#[AuditedValue]` na vlastnostech, které vypovídají o identitě
a oprávněních — `IdentityTrait` (email, username, roles, sso, ssoSub, anonymizedAt,
anonymizedBy, isActive), `AclRoleTrait` (name, acls, isAdmin, needsSso, needs2fa,
sessionExpirationMinutes), `AclTrait`, `SsoTrait`, `ProfileTrait`, `PasskeyTrait`.
Jméno, příjmení, telefon a detaily heslové politiky zůstávají jen v change_logu.
`ConfigurationTrait` hodnotu do auditu nepouští vůbec — do sloupce se vejde cokoliv
včetně tajemství a která konfigurace se změnila, řekne identifikace záznamu.

Hash hesla je zvláštní případ: `IdentityTrait` ho loguje jako
`#[LoggableProperty(withValue: false)]`, tedy **bez hodnoty**. S hodnotou by change_log držel
historii hashů včetně dávno neplatných hesel a při úniku dumpu by to byl materiál na offline
lámání; bez atributu by se naopak změna hesla nezalogovala vůbec.

Záznam nese `payload.changeLogId` a stejnou hodnotu v `correlation_id`, takže z auditu
vede cesta na detail v `change_logu`. Jedna entita má v rámci requestu jeden řádek
`change_logu`, který každý další flush doplní — takový záznam se do auditu zapíše znovu,
celý, s `payload.supersedesPrevious`.

---

## 22. Retence logů (volitelné)

Logovací tabulky rostou donekonečna, dokud je někdo nemaže. Jak dlouho se co drží, je
slib vůči zákazníkovi i regulátorovi, takže to patří na jedno místo — do konfigurace:

```neon
fancyAdmin:
	purge:
		- {entity: App\Model\Entities\RequestLogBody, retention: '1 month'}
		- {entity: App\Model\Entities\RequestLog, retention: '6 months'}
		- {entity: App\Model\Entities\ApiLog, retention: '6 months'}
```

Maže `fancyadmin:purge-logs`, typicky z nočního cronu. Bere `--dry-run` (jen spočítá,
co by smazal) a `--batch-size`.

Každá entita musí mít `createdAt` — maže se podle času vzniku řádku, ne podle obchodních
časů jako „kdy nastala chyba na zařízení". Ty se totiž mohou od vzniku záznamu lišit
a řádek by zmizel dřív, než mu doběhne jeho doba. **Na `created_at` patří index**, jinak
každé mazání projede celou tabulku.

Maže se přes DBAL po dávkách s pauzou; jedno velké `DELETE` nad milionovou tabulkou drží
zámky a utíká s ním replika. Rozbitá položka (překlep v názvu entity) shodí jen svůj řádek
výpisu a příkaz skončí chybou — ostatní tabulky se domažou, aby databáze nerostla všude.

**Pořadí v seznamu rozhoduje**, mazání jde odshora dolů. Záleží na něm tam, kde jsou
tabulky svázané cizím klíčem: `request_log_body` visí na `request_log` s `ON DELETE
CASCADE` a má kratší retenci, takže musí jít první — jinak by ho nejdřív odmazala kaskáda
podle retence rodiče.

`audit_log` sem **nepatří**. Auditní stopu odváží a maže mover, až když ji má bezpečně
v dlouhodobém úložišti; smazat ji podle času by znamenalo ztratit záznamy, které nikde
jinde ještě nejsou.

---

## 23. Odvoz logů do odděleného úložiště (volitelné)

Logovací tabulky v aplikaci jsou jen přestupní stanice. Logy se drží mnohem déle, než má
smysl zatěžovat provozní databázi, a auditní stopa má navíc být **jinde než systém, o kterém
vypovídá** — kdo se dostane k aplikaci, nesmí umět přepsat záznamy o tom, co v ní dělal.

```neon
fancyAdmin:
	logMover:
		connection: @nettrine.dbal.connections.logdb.connection
		tables:
			- {entity: App\Model\Entities\AuditLog, hot: '3 months', retention: '13 months'}
			- {entity: ADT\DoctrineLoggable\Entity\ChangeLog, hot: '3 months', retention: '13 months'}
			- {entity: App\Model\Entities\RequestLog, table: request_log_archive}
			- {entity: App\Model\Entities\TransactionLog, where: 'response_at IS NOT NULL OR created_at < NOW() - INTERVAL 1 DAY'}
```

`hot` a `retention` používá jen `fancyadmin:print-log-schema` (viz níže), odvoz sám ne.

`where` omezuje, co už je zralé na odvoz. Patří sem tabulka, do které se po založení ještě
zapisuje — typicky request teď, response za chvíli: odvezený řádek už aplikace ve zdroji
nenajde a dopsat do něj nedokáže. Podmínka musí pustit dál i záznamy, které se nikdy
nedokončí (proto to `OR created_at < ...`), jinak ve zdroji zůstanou navždy. Uplatní se
při výběru ze zdroje, ne až při mazání — maže se podle id toho, co se opravdu odvezlo.

Odváží `fancyadmin:move-logs`, typicky z cronu. Bere `--dry-run`, `--batch-size` a `--limit`
(strop na tabulku a běh, aby se noční odvoz nezakousl, když se něco nahromadí). Nedostupná
nebo rozbitá tabulka shodí jen svůj řádek výpisu, ostatní se odvezou. Bez konfigurace se
příkaz neregistruje.

**Odvoz vs. mazání:** `purge-logs` záznam zahodí, `move-logs` ho přestěhuje. Do moveru proto
patří i to, co se podle retenční politiky musí uchovat dlouho — auditní stopa, change log,
provozní logy, u kterých jde spíš o velikost provozní databáze než o životnost dat. Tatáž
tabulka nemá být v obou konfiguracích.

### Založení cílového úložiště

```bash
php bin/console fancyadmin:print-log-schema
```

Vypíše SQL k ručnímu spuštění: vytvoření uživatelů a databáze podle nastaveného spojení
(hesla tam schválně nejsou) a `CREATE TABLE` pro každou tabulku z konfigurace. Na PostgreSQL
doplní u tabulek, které mají `hot` nebo `retention`, i hypertable, kompresní a retenční
politiku TimescaleDB.

**Uživatelé jsou dva.** Vlastník (`<vlastnik>`) schéma založí a patří mu retenční politiky;
aplikace dostane účet, který umí jen `SELECT` a `INSERT` — žádné `UPDATE`, `DELETE`, `DROP`
ani `ALTER`. Bez toho celé oddělené úložiště nedává smysl: kdo se dostane k aplikaci, mohl by
přepsat záznamy o tom, co v ní dělal. `SELECT` aplikace potřebovat bude (odvoz podle id
poznává, co už v cíli je, a sekce Logy odtud čtou), mazání zůstává výhradně retenční politice.
Údaje vlastníka se do aplikace nikdy nedostanou.

Schéma se odvozuje **z entit**, takže neodejde od zdroje — přibude sloupec v logu a příští
výpis ho má taky. Ručně psané SQL vedle entit se rozejde a přijde se na to až tím, že odvoz
spadne na neznámém sloupci.

Aplikace ten příkaz nespouští, jen tiskne: do cílového serveru nemá přístup a **to je celý
smysl odděleného úložiště**. Právo mazat tam aplikace mít nemá, jinak by šel odvezený záznam
odstranit odtud, odkud přišel.

### Cílová tabulka

Cílová tabulka má tytéž sloupce jako zdrojová. **Záznam si veze své `id`** — jde podle něj
dohledat zpátky, mover podle něj pozná, co už odvezl, a odkazy mezi odvezenými tabulkami
(`request_log_body.request_log_id`) dál sedí.

Proto ale **cílová databáze patří vždy jen jednomu zdroji**: id se mezi systémy potkávají,
takže dva zdroje v jedné tabulce by si je přepsaly. Každý projekt má vlastní cílovou
databázi. Příklad pro `audit_log`:

```sql
CREATE TABLE audit_log (
    id               BIGINT       NOT NULL,
    action           VARCHAR(255) NOT NULL,
    outcome          VARCHAR(255) NOT NULL,
    created_at       TIMESTAMPTZ  NOT NULL,
    created_by_id    VARCHAR(255),
    created_by_label VARCHAR(255),
    created_by       JSONB,
    source_ip        VARCHAR(45),
    user_agent       TEXT,
    correlation_id   VARCHAR(255),
    payload          JSONB,
    CONSTRAINT audit_log_primary PRIMARY KEY (id, created_at)
);
```

Na TimescaleDB (doporučeno — dělení podle času, komprese starších dat, retenční politika
v databázi místo v cronu) musí být dělicí sloupec v každém unikátním klíči, proto je
`created_at` i v primárním klíči:

```sql
SELECT create_hypertable('audit_log', 'created_at');
SELECT add_compression_policy('audit_log', INTERVAL '3 months');
SELECT add_retention_policy('audit_log', INTERVAL '13 months');
```

Obě doby volte podle toho, co má projekt slíbené, ne podle toho, co se hodí databázi.
Komprese je hranice mezi provozní a archivní vrstvou — komprimovaná data jdou číst dál,
ale s prodlevou na dekompresi, takže pokud dokument slibuje „záznamy za poslední X měsíců
dohledatelné bez prodlevy", je to právě tohle X. Retence je horní mez, po které data
zmizí; kratší hodnota než slíbená dělá z dokumentu nepravdu.

Čas jde ze zdroje v UTC a mover ho posílá s výslovným offsetem. Kdyby ho posílal bez něj,
`TIMESTAMPTZ` by si ho vyložil podle zóny serveru a záznamy by se posunuly — tiše, nic by
nespadlo, jen by přestaly sedět s ostatními logy.

### Pořadí operací

Nejdřív zápis do cíle, pak teprve mazání ve zdroji — a maže se jen to, co se opravdu
zapsalo. Když zápis selže, ze zdroje nezmizí nic. Přeruší-li se běh mezi zápisem a mazáním,
zůstanou záznamy v obou a další běh je podle `(source, source_id)` pozná a přeskočí.

Opačné pořadí (nebo mazání „co se stihlo") znamená ztrátu, kterou nikdo nedohledá, protože
záznam o ní byl právě v tom, co zmizelo.

---

## 24. Přihlašování v administraci (volitelné)

Sekce **Přihlašování** ukazuje v Backoffice, kdo se kdy odkud přihlásil — pro podporu
a diagnostiku. Zdrojem je tabulka `auth_log` z `adt/doctrine-authenticator`.

**Není to auditní stopa.** Ta má být mimo aplikaci právě proto, aby ji nepřepsal nikdo, kdo
se do administrace dostane; tady jde o provozní pohled a co je vidět odsud, není důkaz. Mít
obojí je záměr — dvě kopie téže události s jinou retencí a jiným okruhem čtenářů. Jen ať to
odpovídá tomu, co o přístupu k logům tvrdí politika projektu.

Zapíná se v knihovně:

```neon
security.authenticator:
	setup:
		- setAuthLog(true)
```

Projekt si dodá query (entitu `AuthLog` mapuje knihovna sama) a presenter:

```php
class AuthLogQuery extends BaseQuery implements \ADT\FancyAdmin\Model\Queries\AuthLogQuery
{
	use AuthLogQueryTrait;

	// auth_log je globální systémová tabulka bez vazby na account,
	// sekce je proto jen v Backoffice přes vlastní ACL resource
	protected function applySecurityFilter(): void {}
	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void {}
}

class AuthLogsPresenter extends BasePresenter
{
	use AuthLogsPresenterTrait;
}
```

Plus `AuthLogQueryFactory`, `AuthLogGrid` + `AuthLogGridFactory` (stejným vzorem jako
ostatní gridy) a položka v menu přes `addAuthLogsItem()`.

Retenci si řídí projekt — tabulka patří do `purge` (viz sekce 22), ne do moveru: odvézt ji
do odděleného úložiště by znamenalo, že v administraci nebude vidět, což je přesně to, kvůli
čemu je tady.

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

---

## 24. Log volání cizích rozhraní (volitelné)

Opak `request_log`: ten drží, co přišlo zvenčí, `api_log` to, co aplikace sama poslala ven —
odeslaný požadavek, přijatou odpověď, stavový kód, dobu zpracování a případnou chybu spojení.

```php
#[ORM\Entity]
#[ORM\Index(fields: ['createdAt'])]
#[ORM\Index(fields: ['accountId'])]
#[ORM\Index(fields: ['type'])]
class ApiLog extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\ApiLog
{
	use \ADT\FancyAdmin\Model\Entities\ApiLogTrait;

	// `type` je na projektu: každá aplikace volá jiná rozhraní a chce je mít otypovaná
	#[ORM\Column(enumType: ApiLogTypeEnum::class)]
	protected ApiLogTypeEnum $type;

	// vlastní sloupce projektu, např. vazba na doklad, kvůli kterému se volalo
	#[ORM\Column(nullable: true)]
	protected ?int $documentId = null;
}
```

```neon
services:
	- ADT\FancyAdmin\Model\ApiLogger(@nettrine.dbal.connections.default.connection::getParams())
```

```php
$this->apiLogger->log(
	type: ApiLogTypeEnum::SUBMISSION->value,
	environment: 'playground',
	endpointUrl: $url,
	request: $xml,
	response: $response?->body,
	httpStatusCode: $response?->statusCode,
	durationMs: $durationMs,
	accountId: $account?->getId(),
	errorMessage: $error,
	extraValues: ['document_id' => $document?->getId()],   // systémové sloupce nepřepíšou
);
```

**Vlastní spojení, ne to od EntityManageru.** Volání ven typicky probíhá uvnitř otevřené ORM
transakce a ta se může rozpadnout právě kvůli tomu, co protistrana odpověděla — na sdíleném
spojení by se rollbackem ztratil přesně ten záznam, kvůli kterému se loguje. Zápis přes DBAL
(ne přes entitu) navíc nesahá na rozpracovaný flush. Selhání zápisu se polyká: log nesmí
shodit operaci, o které vypovídá.

Obsah prochází `SensitiveDataSanitizer` — do cizího rozhraní i zpátky můžou téct údaje, které
se do logu uložit nesmí.
