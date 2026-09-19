<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Attributes\Audited;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;

/**
 * Entity pro ChangeLogAuditSubscriber - v projektu je takto oznacena treba Identity
 * nebo AclRole, tady staci trida s atributy, subscriber nad ni jen ctne reflexi.
 */
abstract class TestAuditedBase
{
	/** Privatni vlastnost rodice - getProperties() na potomkovi ji nevidi. */
	#[AuditedValue]
	private ?string $state = null;
}

#[Audited(action: 'identity_change')]
final class TestAuditedEntity extends TestAuditedBase
{
	#[AuditedValue]
	private ?string $email = null;

	#[AuditedValue]
	private ?object $role = null;

	#[AuditedValue]
	private array $roles = [];

	/** Bez atributu - do auditu se dostane jen jako nazev zmenene vlastnosti. */
	private ?string $phoneNumber = null;
}

/** Logovana do change_logu, ale bez #[Audited] - do auditu nepatri. */
final class TestNotAuditedEntity
{
	private ?string $title = null;
}
