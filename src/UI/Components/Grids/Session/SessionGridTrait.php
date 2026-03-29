<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Session;

use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineAuthenticator\StorageEntity;
use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;

trait SessionGridTrait
{
	use AuthenticatorInject;

	protected function setGridDataSource(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;
		$grid->setPagination(false);

		$objectId = $this->getSecurityUser()->getIdentity()->getAuthObjectId();
		$currentSessionId = $this->_authenticator->getCurrentSessionId();
		$sessions = $this->_authenticator->getActiveSessions($objectId);

		$data = [];
		foreach ($sessions as $session) {
			/** @var StorageEntity $session */
			$data[] = [
				'id' => $session->getId(),
				'ip' => $session->getIp(),
				'userAgent' => $this->parseUserAgent($session->getUserAgent()),
				'createdAt' => $session->getCreatedAt()->format('d.m.Y H:i'),
				'validUntil' => $session->getValidUntil()->format('d.m.Y H:i'),
				'isCurrent' => $session->getId() === $currentSessionId,
			];
		}

		$grid->setDataSource($data);
	}

	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('ip', 'fcadmin.grids.session.ip');

		$grid->addColumnText('userAgent', 'fcadmin.grids.session.userAgent');

		$grid->addColumnText('createdAt', 'fcadmin.grids.session.createdAt');

		$grid->addColumnText('validUntil', 'fcadmin.grids.session.validUntil');

		$grid->addColumnText('isCurrent', 'fcadmin.grids.session.isCurrent')
			->setRenderer(function (array $row): string {
				return $row['isCurrent']
					? '<span class="badge bg-success">' . $this->getTranslator()->translate('fcadmin.grids.session.currentDevice') . '</span>'
					: '';
			})
			->setTemplateEscaping(false);

		$grid->addAction('invalidate', 'fcadmin.grids.session.invalidate', 'invalidate!')
			->setIcon('sign-out-alt')
			->setClass('btn btn-danger btn-sm ajax')
			->setRenderCondition(function (array $row): bool {
				return !$row['isCurrent'];
			});
	}

	public function handleInvalidate(int $id): void
	{
		$this->_authenticator->clearSession($id);
		$this->redirect('this');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return '';
	}

	private function parseUserAgent(?string $userAgent): string
	{
		if ($userAgent === null) {
			return '';
		}

		if (preg_match('/(?:Chrome|CriOS)\/[\d.]+/', $userAgent) && !str_contains($userAgent, 'Edg')) {
			$browser = 'Chrome';
		} elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
			$browser = 'Firefox';
		} elseif (preg_match('/Edg\/[\d.]+/', $userAgent)) {
			$browser = 'Edge';
		} elseif (preg_match('/Safari\/[\d.]+/', $userAgent) && !str_contains($userAgent, 'Chrome')) {
			$browser = 'Safari';
		} elseif (preg_match('/OPR\/[\d.]+/', $userAgent)) {
			$browser = 'Opera';
		} else {
			return $userAgent;
		}

		if (str_contains($userAgent, 'Windows')) {
			$os = 'Windows';
		} elseif (str_contains($userAgent, 'Macintosh')) {
			$os = 'macOS';
		} elseif (str_contains($userAgent, 'Linux')) {
			$os = 'Linux';
		} elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
			$os = 'iOS';
		} elseif (str_contains($userAgent, 'Android')) {
			$os = 'Android';
		} else {
			$os = '';
		}

		return $os ? "$browser ($os)" : $browser;
	}
}
