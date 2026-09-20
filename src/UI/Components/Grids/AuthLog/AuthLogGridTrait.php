<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\AuthLog;

use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineAuthenticator\AuthLog;
use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;

/**
 * Přehled přihlašování v administraci - "kdo se kdy odkud přihlásil" pro podporu.
 *
 * Zdrojem je tabulka auth_log z adt/doctrine-authenticator (zapíná se přes setAuthLog()).
 * NENÍ to auditní stopa: ta je mimo aplikaci právě proto, aby ji nepřepsal nikdo, kdo se
 * do administrace dostane. Tady jde o provozní pohled a podle toho se k němu chovej -
 * co je vidět odsud, není důkaz.
 */
trait AuthLogGridTrait
{
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;

		$grid->setDefaultSort(['createdAt' => 'DESC']);

		$grid->addColumnDateTime('createdAt', 'fcadmin.grids.authLog.createdAt');

		$grid->addColumnText('type', 'fcadmin.grids.authLog.type')
			->setRenderer(function (AuthLog $authLog) {
				return $this->getTranslator()->translate('fcadmin.grids.authLog.types.' . $authLog->getType());
			});

		// prihlasovaci jmeno tak, jak ho uzivatel zadal - ucet toho jmena nemusi existovat,
		// a prave to je u neuspesnych pokusu ta zajimava informace
		$grid->addColumnText('identity', 'fcadmin.grids.authLog.identity');

		$grid->addColumnText('ip', 'fcadmin.grids.authLog.ip');

		$grid->addColumnText('userAgent', 'fcadmin.grids.authLog.userAgent');

		$grid->addColumnText('reason', 'fcadmin.grids.authLog.reason')
			->setRenderer(function (AuthLog $authLog) {
				$reason = $authLog->getReason();
				if ($reason === null) {
					return '—';
				}

				// trida vyjimky sama o sobe nikomu nic nerekne, zkratime ji na nazev
				$parts = explode('\\', $reason);

				return end($parts);
			});

		$grid->addFilterSelect('type', 'fcadmin.grids.authLog.type', $this->getTypeOptions())
			->setPrompt('—');

		$this->addSearchFilter($grid, ['identity', 'ip']);
	}

	/** @return array<string, string> */
	private function getTypeOptions(): array
	{
		$options = [];
		foreach ([
			DoctrineAuthenticator::TYPE_LOGIN,
			DoctrineAuthenticator::TYPE_LOGIN_FAILED,
			DoctrineAuthenticator::TYPE_LOGIN_BLOCKED,
			DoctrineAuthenticator::TYPE_LOGOUT,
			DoctrineAuthenticator::TYPE_FRAUD_DETECTED,
			DoctrineAuthenticator::TYPE_INVALID_TOKEN,
		] as $_type) {
			$options[$_type] = $this->getTranslator()->translate('fcadmin.grids.authLog.types.' . $_type);
		}

		return $options;
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return \ADT\FancyAdmin\Model\Queries\Factories\AuthLogQueryFactory::class;
	}
}
