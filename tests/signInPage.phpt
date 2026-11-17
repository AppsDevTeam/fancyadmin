<?php

declare(strict_types=1);

/**
 * BasePresenterTrait::isSignInPage() - na které stránce karta přihlášení nekreslí odkaz „přejít na přihlášení“.
 */

namespace {

	require __DIR__ . '/bootstrap.php';
}

namespace SignInPageTest {

	use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
	use Nette\Application\UI\Presenter;

	class PortalPresenter extends Presenter
	{
		use BasePresenterTrait;

		public ?string $currentLink = null;

		public function isLinkCurrent(?string $destination = null, $args = []): bool
		{
			return $destination === $this->currentLink;
		}

		public function signInPage(): bool
		{
			return $this->isSignInPage();
		}
	}

	final class OwnSignInPresenter extends PortalPresenter
	{
		protected function isSignInPage(): bool
		{
			return $this->isLinkCurrent('Sign:in');
		}
	}
}

namespace {

	use Tester\Assert;

	test('vychozi prihlaseni je Portal:Sign:in', function () {
		$presenter = new SignInPageTest\PortalPresenter();
		Assert::false($presenter->signInPage());

		$presenter->currentLink = ':Portal:Sign:in';
		Assert::true($presenter->signInPage());
	});

	test('projekt s vlastnim prihlasenim stranku prepise', function () {
		$presenter = new SignInPageTest\OwnSignInPresenter();
		$presenter->currentLink = ':Portal:Sign:in';
		Assert::false($presenter->signInPage());

		$presenter->currentLink = 'Sign:in';
		Assert::true($presenter->signInPage());
	});

	test('layout se na stranku prihlaseni pta pres promennou z beforeRender', function () {
		$layout = file_get_contents(__DIR__ . '/../src/UI/Presenters/@layout.latte');

		Assert::contains('{if !$user->isLoggedIn() && !$isSignInPage}', $layout);
		Assert::notContains("isLinkCurrent(':Portal:Sign:in')", $layout);
	});
}
