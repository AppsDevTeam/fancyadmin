<?php

declare(strict_types=1);

/**
 * AuthPresenterTrait::getMenuFactoryNamespace() - kde trait hledá NavbarMenuFactory a UserMenuFactory modulu.
 */

namespace {

	require __DIR__ . '/bootstrap.php';
}

namespace MenuTest\UI\Backoffice\Presenters\Order {

	use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;
	use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
	use Nette\Application\UI\Presenter;

	abstract class MenuBasePresenter extends Presenter
	{
		use BasePresenterTrait;
	}

	final class OrderPresenter extends MenuBasePresenter
	{
		use AuthPresenterTrait;

		public function menuNamespace(): string
		{
			return $this->getMenuFactoryNamespace();
		}
	}
}

namespace MenuTest\Modules\AdminModule\Order {

	use ADT\FancyAdmin\UI\Presenters\AuthPresenterTrait;
	use MenuTest\UI\Backoffice\Presenters\Order\MenuBasePresenter;

	final class OrderPresenter extends MenuBasePresenter
	{
		use AuthPresenterTrait;

		public function menuNamespace(): string
		{
			return $this->getMenuFactoryNamespace();
		}
	}

	final class OwnNamespacePresenter extends MenuBasePresenter
	{
		use AuthPresenterTrait;

		public function menuNamespace(): string
		{
			return $this->getMenuFactoryNamespace();
		}

		protected function getMenuFactoryNamespace(): string
		{
			return 'MenuTest\Modules\AdminModule';
		}
	}
}

namespace {

	use Tester\Assert;

	test('vychozi namespace je <modul>\Presenters podle tridy presenteru', function () {
		Assert::same(
			'MenuTest\UI\Backoffice\Presenters',
			(new MenuTest\UI\Backoffice\Presenters\Order\OrderPresenter())->menuNamespace(),
		);
	});

	test('presenter mimo \Presenters\ dostane srozumitelnou chybu, ne nesmyslny namespace', function () {
		Assert::exception(
			fn() => (new MenuTest\Modules\AdminModule\Order\OrderPresenter())->menuNamespace(),
			LogicException::class,
			'%a%přepište getMenuFactoryNamespace()%a%',
		);
	});

	test('projekt namespace prepise', function () {
		Assert::same(
			'MenuTest\Modules\AdminModule',
			(new MenuTest\Modules\AdminModule\Order\OwnNamespacePresenter())->menuNamespace(),
		);
	});
}
