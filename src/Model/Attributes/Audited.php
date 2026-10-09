<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Attributes;

use Attribute;

/**
 * Změny této entity patří vedle change_logu i do auditní stopy (audit_log).
 *
 * Do change_logu jde všechno, co má #[LoggableEntity] - je to provozní historie
 * a smí být hustá. Auditní stopa je něco jiného: bezpečnostní záznam, který se
 * dlouhodobě archivuje, odváží do centrálního systému a čte ho auditor. Proto se
 * do ní entity vybírají jmenovitě, tímto atributem, a ne naopak vylučují.
 *
 * PRO ENTITY FANCYADMINU HO NEPOTŘEBUJETE: identity, oprávnění, účty, profily
 * a konfigurace se auditují samy podle rozhraní, které implementují - viz
 * ChangeLogAuditSubscriber::DEFAULT_ACTIONS. Tenhle atribut je pro entity projektu
 * a pro případ, kdy projektu nesedí výchozí akce a chce ji přepsat (atribut vyhrává).
 *
 * Akce se zafixuje prvním nasazením: audit_log je append-only, takže ji nejde
 * zpětně přejmenovat, aniž by starým záznamům přestal rozumět dotaz nad novými.
 * Volí se proto podle domény, ne podle entity - detekční pravidla se pak klíčují
 * na jednu hodnotu místo výčtu tříd:
 *
 *   #[Audited(action: 'identity_change')]   // Identity, Passkey, ApiKey, Sso
 *   #[Audited(action: 'acl_change')]        // Acl, AclRole, AclResource
 *
 * Hodnoty vlastností se do záznamu nedostanou samy - viz AuditedValue.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Audited
{
	public function __construct(
		public readonly string $action,
	) {
	}
}
