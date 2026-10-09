<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Audit;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Http\IRequest;

/**
 * Snapshot aktéra pro auditní záznam - kdo a odkud.
 *
 * Denormalizovaný, bez relace na identity: audit nesmí záviset na zbytku databáze,
 * aby ho šlo odvézt do dlouhodobého úložiště beze ztráty významu, i kdyby uživatel
 * v aplikaci mezitím zanikl.
 *
 * V kontextu bez uživatele (cron, konzument fronty, CLI) zůstává prázdný - to je
 * platný stav, ne chyba: systémová akce aktéra nemá.
 */
final readonly class AuditActor
{
	public function __construct(
		private SecurityUser $securityUser,
		private IRequest $httpRequest,
	) {
	}

	/**
	 * @return array{id: string|null, label: string|null, data: array, ip: string|null, userAgent: string|null}
	 */
	public function create(): array
	{
		$identity = $this->securityUser->isLoggedIn() ? $this->securityUser->getIdentity() : null;

		return [
			'id' => $identity !== null ? (string) $identity->getId() : null,
			'label' => $identity instanceof Identity ? ($identity->getFullName() ?: $identity->getEmail()) : null,
			'data' => $identity instanceof Identity
				? ['name' => $identity->getFullName(), 'email' => $identity->getEmail()]
				: [],
			'ip' => $this->httpRequest->getRemoteAddress(),
			'userAgent' => $this->httpRequest->getHeader('User-Agent'),
		];
	}
}
