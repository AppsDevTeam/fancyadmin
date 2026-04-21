<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Sso;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\Queries\Factories\SsoQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;

trait SsoGridTrait
{
	use Editable;
	use IdentityQueryFactoryInject;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'fcadmin.presenters.sso.grid.name');
		$grid->addColumnText('realm', 'fcadmin.presenters.sso.grid.realm');
		$grid->addColumnText('hostUrl', 'fcadmin.presenters.sso.grid.hostUrl')
			->setRenderer(fn(Sso $sso) => \Nette\Utils\Html::el('a')
				->href($sso->getHostUrl())
				->setAttribute('target', '_blank')
				->setText($sso->getHostUrl())
			);
		$grid->addColumnText('clientId', 'fcadmin.presenters.sso.grid.clientId');
		$grid->addColumnText('defaultRole', 'fcadmin.presenters.sso.grid.defaultRole')
			->setRenderer(fn(Sso $sso) => $sso->getDefaultRole()?->getName());

		$grid->addAction('removeSso', 'fcadmin.presenters.sso.grid.delete', 'removeSso!')
			->setIcon('trash')
			->setClass('ajax datagrid-delete')
			->setConfirmation(new StringConfirmation('fcadmin.presenters.sso.confirms.delete'));
	}

	public function handleRemoveSso(int $id): void
	{
		$sso = $this->getEntityManager()->find(
			$this->getEntityManager()->findEntityClassByInterface(Sso::class),
			$id
		);

		if ($sso === null) {
			$this->getPresenter()->flashMessageError('fcadmin.appGeneral.exceptions.userNotFound');
			$this->getPresenter()->redirect('this');
		}

		$hasIdentities = $this->_identityQueryFactory->create()
			->disableSecurityFilter()
			->by('sso', $sso)
			->count() > 0;

		if ($hasIdentities) {
			$this->getPresenter()->flashMessageError('fcadmin.presenters.sso.errors.cannotDeleteHasIdentities');
			$this->getPresenter()->redirect('this');
		}

		$this->getEntityManager()->remove($sso);
		$this->getEntityManager()->flush();

		$this->getPresenter()->flashMessageSuccess('fcadmin.presenters.sso.messages.deleted');
		$this->getPresenter()->redirect('this');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return SsoQueryFactory::class;
	}
}
