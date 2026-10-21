<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

/**
 * Rozdeleni seznamu uzivatelu na bezne uzivatele a spravce. Nikde se neuklada -
 * odvozuje se z toho, jestli ma identita roli primo na sobe (Identity::$roles).
 * Role z profilu se nepocitaji, ty ma i bezny zakaznicky uzivatel.
 *
 * Scope je volitelny: kde se nepouzije, seznam zustava nerozdeleny.
 */
enum IdentityScopeEnum: string
{
	/** Uzivatel bez role primo na identite, tedy jen s profily. */
	case PROFILE = 'profile';

	/** Uzivatel s roli primo na identite, tedy nekdo, kdo spravuje system. */
	case STAFF = 'staff';

	public function isStaff(): bool
	{
		return $this === self::STAFF;
	}

	/** Klic pro popisek v menu. */
	public function titleKey(): string
	{
		return 'fcadmin.presenters.identity.scope.' . $this->value . '.title';
	}

	/** Klic pro titulek stranky. */
	public function pageTitleKey(): string
	{
		return 'fcadmin.presenters.identity.scope.' . $this->value . '.pageTitle';
	}

	/** Klic pro nadpis stranky. */
	public function headerKey(): string
	{
		return 'fcadmin.presenters.identity.scope.' . $this->value . '.header';
	}
}
