<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Attributes;

use Attribute;

/**
 * Do auditní stopy se u této vlastnosti uloží i stará a nová hodnota.
 *
 * Bez tohoto atributu nese záznam jen NÁZEV změněné vlastnosti. Auditor se ptá
 * "kdo komu kdy změnil roli", ne "jaké přesně bylo telefonní číslo" - a auditní
 * stopa se archivuje mnohem déle než provozní data, takže co do ní jednou spadne,
 * tam zůstane i po smazání účtu. Hodnoty se proto vybírají jmenovitě: role, stav
 * účtu, příznak zaplacení. Hesla, tokeny a osobní údaje sem nepatří; detail
 * zůstává v change_logu, kam z auditního záznamu vede changeLogId.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class AuditedValue
{
}
