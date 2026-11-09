<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Tablets;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\TabletFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\TabletQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\TabletServiceInject;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Grids\Tablet\TabletGrid;
use ADT\FancyAdmin\UI\Components\Grids\Tablet\TabletGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use Nette\Application\ForbiddenRequestException;

/**
 * Správa tabletů (docs/tablets.md): spárování ukáže QR kód s jednorázovým odkazem, tablet
 * ho naskenuje a od té doby mu formuláře chodí push notifikací (TabletService). Tablet se
 * do administrace nepřihlašuje.
 */
trait TabletsPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use TabletFormFactoryInject;
	use TabletQueryFactoryInject;
	use TabletServiceInject;

	protected ?Tablet $tablet = null;

	/** Absolutní odkaz na párování v presenteru s TabletDevicePresenterTrait, např. $this->link('//:Client:Tablet:pair', ['token' => $token]) */
	abstract protected function getTabletPairingUrl(string $token): string;

	/** QR kód odkazu jako SVG (knihovnu na QR má projekt) */
	abstract protected function getTabletQrSvg(string $url): string;

	public function actionDefault(): void
	{
		$this->template->pairingTablet = null;
		$this->setPackageTemplateFile(__DIR__ . '/default.latte');
	}

	public function handleNew(): void
	{
		$this->requireTabletEditAllowed();
		$this->tablet = null;
		$this->redrawSidePanel();
	}

	public function handleEdit(Tablet $tablet): void
	{
		$this->requireTabletEditAllowed();
		$this->tablet = $tablet;
		$this->redrawSidePanel();
	}

	/**
	 * QR kód s jednorázovým párovacím odkazem. Ukáže se jen v odpovědi na tento
	 * požadavek - v DB je jen hash, po obnovení stránky už vidět není.
	 */
	public function handlePair(int $id): void
	{
		$this->requireTabletEditAllowed();
		$tablet = $this->getTablet($id);

		$url = $this->getTabletPairingUrl($this->_tabletService->createPairingToken($tablet));
		$this->template->pairingTablet = $tablet;
		$this->template->pairingUrl = $url;
		$this->template->pairingQrSvg = $this->getTabletQrSvg($url);
		$this->redrawControl('pairing');
	}

	public function handleDisconnect(int $id): void
	{
		$this->requireTabletEditAllowed();
		$tablet = $this->getTablet($id);
		$this->_tabletService->disconnect($tablet);

		$this->flashMessageSuccess('fcadmin.tablets.messages.disconnected', parameters: ['name' => $tablet->getName()]);
		$this->redrawControl('flashes');
		$this['tabletGrid']->getGrid()->reload();
	}

	/** Párování, odpojení a úpravy - projekt přepíše podle svých oprávnění */
	public function canEditTablets(): bool
	{
		return true;
	}

	public function createComponentTabletGrid(TabletGridFactory $factory): TabletGrid
	{
		return $factory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->tablet;
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_tabletFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_tabletQueryFactory->create();
	}

	private function getTablet(int $id): Tablet
	{
		/** @var Tablet|null $tablet */
		$tablet = $this->_tabletQueryFactory->create()->byId($id)->fetchOneOrNull();
		return $tablet ?? $this->error();
	}

	/** @throws ForbiddenRequestException */
	private function requireTabletEditAllowed(): void
	{
		if (!$this->canEditTablets()) {
			throw new ForbiddenRequestException();
		}
	}
}
