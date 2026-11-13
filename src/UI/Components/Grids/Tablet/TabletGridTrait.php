<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Tablet;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\EditParams;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;
use ADT\FancyAdmin\UI\Presenters\Tablets\TabletsPresenterTrait;
use Nette\Utils\Html;

/**
 * Tablety - stav spárování a notifikací, akce Spárovat a Odpojit (TabletsPresenterTrait).
 */
trait TabletGridTrait
{
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;

		$this->addSearchFilter($grid, ['name']);

		$grid->addColumnText('name', 'fcadmin.tablets.grid.name');

		$grid->addColumnText('state', 'fcadmin.tablets.grid.state')
			->setRenderer(fn(Tablet $tablet): Html => $this->renderTabletState($tablet));

		$grid->addColumnText('lastSeenAt', 'fcadmin.tablets.grid.lastSeenAt')
			->setRenderer(fn(Tablet $tablet): string => $tablet->getLastSeenAt()?->format('j. n. Y G:i') ?? '—');

		if ($this->canEditTablets()) {
			$grid->addAction('pair', 'fcadmin.tablets.grid.pair', 'pair!')
				->setIcon('qrcode')
				->setClass('')
				->setTitle('fcadmin.tablets.grid.pairTitle');

			$grid->addAction('disconnect', 'fcadmin.tablets.grid.disconnect', 'disconnect!')
				->setIcon('link-slash')
				->setClass('')
				->setTitle('fcadmin.tablets.grid.disconnectTitle')
				->setDataAttribute('confirm', $this->getTranslator()->translate('fcadmin.tablets.grid.disconnectConfirm'))
				// Ne allowRowsAction() - akce v rozbalovacím menu ho šablona gridu nerespektuje
				->setRenderCondition(fn(Tablet $tablet): bool => $tablet->isPaired());
		}
	}

	protected function renderTabletState(Tablet $tablet): Html
	{
		$translator = $this->getTranslator();
		if ($tablet->canReceiveForms()) {
			return Html::el('span')->class('badge bg-success')->setText($translator->translate('fcadmin.tablets.grid.ready'));
		}
		if ($tablet->isPaired()) {
			return Html::el('span')->class('badge bg-warning text-dark')->setText($translator->translate('fcadmin.tablets.grid.notificationsDisabled'))
				// Formulář dorazí i tak, jen tablet nejde probudit
				->setAttribute('title', $translator->translate('fcadmin.tablets.grid.notificationsDisabledHint'));
		}

		return Html::el('span')->class('badge bg-secondary')->setText($translator->translate('fcadmin.tablets.grid.notPaired'));
	}

	protected function allowEdit(): ?EditParams
	{
		return $this->canEditTablets() ? new EditParams('edit!') : null;
	}

	public function handlePair(int $id): void
	{
		$this->getTabletsPresenter()->handlePair($id);
	}

	public function handleDisconnect(int $id): void
	{
		$this->getTabletsPresenter()->handleDisconnect($id);
	}

	protected function canEditTablets(): bool
	{
		return $this->getTabletsPresenter()->canEditTablets();
	}

	/** @return \Nette\Application\UI\Presenter&TabletsPresenterTrait */
	private function getTabletsPresenter(): object
	{
		return $this->getPresenter();
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return TabletQueryFactory::class;
	}
}
