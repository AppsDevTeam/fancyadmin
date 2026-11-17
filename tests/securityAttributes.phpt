<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;
use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use Nette\Security\Resource;
use Nette\Security\User;
use Tester\Assert;

/**
 * AuthPresenterTrait::validateSecurityAttributes() - #[SecurityCheckAttribute] na action/render/handle metodach.
 */

require __DIR__ . '/bootstrap.php';

final class AllowedResourcesUser extends User implements SecurityUser
{
	/** @param list<string> $allowed */
	public function __construct(private array $allowed)
	{
	}

	public function isAllowed(mixed $resource = Authorizator::All, mixed $privilege = Authorizator::All): bool
	{
		return in_array($resource instanceof Resource ? $resource->getResourceId() : $resource, $this->allowed, true);
	}

	public function isAllowedFullDataAclResource(): bool
	{
		return false;
	}

	public function isAllowedBackoffice(): bool
	{
		return true;
	}

	public function isMultiTenant(): bool
	{
		return false;
	}

	public function canSelectAccount(): bool
	{
		return false;
	}

	public function isAdmin(): bool
	{
		return false;
	}

	public function login(string|IIdentity $username, ?string $password = null, ?string $context = null, array $metadata = []): void
	{
	}
}

abstract class SecuredBasePresenter extends Presenter
{
	use BasePresenterTrait;
}

/**
 * Presenter s AuthPresenterTrait bez DI. Uzivatel portalu se podstrci do `_securityUser`, uzivatel
 * z getUser() (u projektu s webem zakaznik) do privatni property Presenteru.
 */
final class SecuredPresenter extends SecuredBasePresenter
{
	use AuthPresenterTrait;

	/**
	 * @param list<string> $allowed prava uzivatele portalu
	 * @param list<string> $presenterUserAllowed prava uzivatele z getUser()
	 */
	public static function create(string $action, array $allowed = [], array $presenterUserAllowed = []): self
	{
		$presenter = new self();
		$presenter->changeAction($action);
		new ReflectionProperty(self::class, '_securityUser')->setValue($presenter, new AllowedResourcesUser($allowed));
		new ReflectionProperty(Presenter::class, 'user')->setValue($presenter, new AllowedResourcesUser($presenterUserAllowed));

		return $presenter;
	}

	public function validate(ReflectionClass|ReflectionMethod $element): bool
	{
		return $this->validateSecurityAttributes($element);
	}

	#[SecurityCheckAttribute(new StringResource('admin.orders'))]
	public function actionOrders(): void
	{
	}

	public function renderOrders(): void
	{
	}

	public function actionPlain(): void
	{
	}

	public function renderEmails(): void
	{
	}

	#[SecurityCheckAttribute(new StringResource('admin.exports'))]
	public function renderExport(): void
	{
	}

	#[SecurityCheckAttribute(new StringResource('admin.delete'))]
	public function handleDelete(): void
	{
	}

	#[SecurityCheckAttribute(new StringResource('admin.warehouse'))]
	#[SecurityCheckAttribute(new StringResource('warehouse.warehouses'))]
	public function actionWarehouses(): void
	{
	}
}

test('akce jen s render metodou nespadne a atribut nema', function () {
	$presenter = SecuredPresenter::create('emails');

	Assert::false($presenter->validate(new ReflectionClass(SecuredPresenter::class)));
	Assert::false($presenter->validate(new ReflectionMethod(SecuredPresenter::class, 'renderEmails')));
});

test('akce bez action i render metody nespadne', function () {
	Assert::false(SecuredPresenter::create('missing')->validate(new ReflectionClass(SecuredPresenter::class)));
});

test('atribut na action metode se kontroluje', function () {
	Assert::true(SecuredPresenter::create('orders', ['admin.orders'])->validate(new ReflectionClass(SecuredPresenter::class)));

	Assert::exception(
		fn() => SecuredPresenter::create('orders')->validate(new ReflectionClass(SecuredPresenter::class)),
		ForbiddenRequestException::class,
	);
});

test('action metoda bez atributu nic nenajde', function () {
	Assert::false(SecuredPresenter::create('plain')->validate(new ReflectionClass(SecuredPresenter::class)));
});

test('akce bez action metody bere atribut z render metody', function () {
	Assert::true(SecuredPresenter::create('export', ['admin.exports'])->validate(new ReflectionClass(SecuredPresenter::class)));

	Assert::exception(
		fn() => SecuredPresenter::create('export')->validate(new ReflectionClass(SecuredPresenter::class)),
		ForbiddenRequestException::class,
	);
});

test('signal kontroluje svuj atribut i u akce bez action metody', function () {
	$handle = new ReflectionMethod(SecuredPresenter::class, 'handleDelete');

	Assert::false(SecuredPresenter::create('emails', ['admin.delete'])->validate($handle));

	Assert::exception(
		fn() => SecuredPresenter::create('emails')->validate($handle),
		ForbiddenRequestException::class,
	);
});

// Projekt s webem dava presenteru pres getUser() zakaznika, prava administrace se ptaji uzivatele portalu
test('prava kontroluje uzivatel portalu, ne uzivatel z getUser()', function () {
	$attributeOn = new ReflectionClass(SecuredPresenter::class);

	Assert::true(SecuredPresenter::create('orders', allowed: ['admin.orders'], presenterUserAllowed: [])->validate($attributeOn));

	Assert::exception(
		fn() => SecuredPresenter::create('orders', allowed: [], presenterUserAllowed: ['admin.orders'])->validate($attributeOn),
		ForbiddenRequestException::class,
	);
});

test('akce s vic atributy vyzaduje vsechna prava', function () {
	$presenter = new ReflectionClass(SecuredPresenter::class);

	Assert::true(SecuredPresenter::create('warehouses', ['admin.warehouse', 'warehouse.warehouses'])->validate($presenter));

	foreach ([['admin.warehouse'], ['warehouse.warehouses']] as $allowed) {
		Assert::exception(
			fn() => SecuredPresenter::create('warehouses', $allowed)->validate($presenter),
			ForbiddenRequestException::class,
		);
	}
});
