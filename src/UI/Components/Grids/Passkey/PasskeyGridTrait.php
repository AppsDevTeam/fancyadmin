<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Passkey;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\PasskeyQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Nette\Utils\Html;

/**
 * Grid přihlašovacích klíčů na Account stránce — vždy jen klíče přihlášené identity.
 */
trait PasskeyGridTrait
{
	use PasskeyQueryFactoryInject;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;
		$grid->setPagination(false);

		$grid->addColumnText('name', 'fcadmin.passkeys.grid.name');

		$grid->addColumnText('createdAt', 'fcadmin.passkeys.grid.createdAt')
			->setRenderer(fn(Passkey $passkey) => $passkey->getCreatedAt()->format('d.m.Y H:i'));

		$grid->addColumnText('lastUsedAt', 'fcadmin.passkeys.grid.lastUsedAt')
			->setRenderer(fn(Passkey $passkey) => $passkey->getLastUsedAt()?->format('d.m.Y H:i') ?? '');

		$grid->addColumnText('backupState', '')
			->setRenderer(fn(Passkey $passkey) => $passkey->getBackupState()
				? Html::el('span')
					->class('badge bg-success')
					->setText($this->getTranslator()->translate('fcadmin.passkeys.grid.synced'))
				: '');

		$grid->addAction('deletePasskey', 'fcadmin.passkeys.grid.delete', 'deletePasskey!')
			->setIcon('trash')
			->setClass('btn btn-danger btn-sm ajax datagrid-delete')
			->setConfirmation(new StringConfirmation('fcadmin.passkeys.confirms.delete'));
	}

	public function handleDeletePasskey(int $id): void
	{
		// Mazat lze jen klíče patřící přihlášené identitě
		/** @var Passkey|null $passkey */
		$passkey = $this->_passkeyQueryFactory->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byIdentity($this->getSecurityUser()->getIdentity())
			->byId($id)
			->fetchOneOrNull();

		if ($passkey === null) {
			$this->getPresenter()->error();
		}

		$this->getEntityManager()->remove($passkey);
		$this->getEntityManager()->flush();

		$this->getPresenter()->flashMessageSuccess('fcadmin.passkeys.messages.deleted');
		$this->getPresenter()->redirect('this');
	}

	protected function initQueryObject($queryObject): void
	{
		$queryObject
			->disableSecurityFilter()
			->disableAccountFilter()
			->byIdentity($this->getSecurityUser()->getIdentity());
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return PasskeyQueryFactory::class;
	}
}
