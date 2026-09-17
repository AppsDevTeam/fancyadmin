<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Passkey;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\PasskeyQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\PasskeyServiceInject;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Nette\Utils\Html;

/**
 * Grid přihlašovacích klíčů na stránce Profil — vždy jen klíče přihlášené identity.
 */
trait PasskeyGridTrait
{
	use PasskeyQueryFactoryInject;
	use PasskeyServiceInject;

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

		// Skrytí tlačítka je jen kosmetika, rozhoduje handleDeletePasskey()
		$isDeleteAllowed = !$this->isLastRequiredPasskey();

		$grid->addAction('deletePasskey', 'fcadmin.passkeys.grid.delete', 'deletePasskey!')
			->setIcon('trash')
			->setClass('btn btn-danger btn-sm ajax datagrid-delete')
			->setConfirmation(new StringConfirmation('fcadmin.passkeys.confirms.delete'))
			->setRenderCondition(fn(Passkey $passkey): bool => $isDeleteAllowed);
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

		// Smazání posledního klíče by identitu s vynuceným 2FA downgradovalo na heslo
		if ($this->isLastRequiredPasskey()) {
			$this->getPresenter()->flashMessageError('fcadmin.passkeys.errors.lastKeyRequired');
			$this->getPresenter()->redirect('this');
		}

		$this->getEntityManager()->remove($passkey);
		$this->getEntityManager()->flush();

		$this->getPresenter()->flashMessageSuccess('fcadmin.passkeys.messages.deleted');
		$this->getPresenter()->redirect('this');
	}

	private function isLastRequiredPasskey(): bool
	{
		$identity = $this->getSecurityUser()->getIdentity();

		if (!$this->_passkeyService->isPasskeyRequired($identity)) {
			return false;
		}

		return $this->_passkeyQueryFactory->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byIdentity($identity)
			->count() <= 1;
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
