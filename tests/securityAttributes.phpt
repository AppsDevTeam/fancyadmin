<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;
use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;
use Nette\Security\Authorizator;
use Nette\Security\Resource;
use Nette\Security\User;
use Tester\Assert;

/**
 * AuthPresenterTrait::validateSecurityAttributes() - #[SecurityCheckAttribute] na action/render/handle metodach.
 */

require __DIR__ . '/bootstrap.php';

final class AllowedResourcesUser extends User
{
	/** @param list<string> $allowed */
	public function __construct(private array $allowed)
	{
	}

	public function isAllowed(mixed $resource = Authorizator::All, mixed $privilege = Authorizator::All): bool
	{
		return in_array($resource instanceof Resource ? $resource->getResourceId() : $resource, $this->allowed, true);
	}
}

abstract class SecuredBasePresenter extends Presenter
{
	use BasePresenterTrait;
}

/**
 * Presenter s AuthPresenterTrait bez DI, uzivatel se podstrci do privatni property Presenteru.
 */
final class SecuredPresenter extends SecuredBasePresenter
{
	use AuthPresenterTrait;

	/** @param list<string> $allowed */
	public static function create(string $action, array $allowed = []): self
	{
		$presenter = new self();
		$presenter->changeAction($action);
		new ReflectionProperty(Presenter::class, 'user')->setValue($presenter, new AllowedResourcesUser($allowed));

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
