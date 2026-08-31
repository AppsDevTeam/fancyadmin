<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Audit;

use ADT\FancyAdmin\Model\Entities\AuditLog;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zapisuje auditni udalosti do jednotne tabulky audit_log.
 *
 * Knihovny (adt/exporter, adt/doctrine-authenticator) o teto tride ani
 * o tabulce nevi - deklaruji si vlastni rozhrani a fancyadmin ho naplni
 * pres adaptery v tomto namespace. Diky tomu na fancyadmin nezavisi.
 *
 * Zapisuje se pres DBAL, ne pres ORM: auditni radek nema co delat
 * v identity mape a nesmi ho ovlivnit flush rozpracovanych entit.
 */
final class AuditLogger
{
	public const string OUTCOME_SUCCESS = 'success';
	public const string OUTCOME_FAILURE = 'failure';

	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
	}

	/**
	 * @param array{id: string|null, label: string|null, data: array, ip: string|null, userAgent: string|null} $actor
	 * @param bool $detached TRUE = vlastni spojeni, tedy MIMO probihajici
	 *        transakci. Zaznam pak prezije jeji rollback - to potrebuje
	 *        autentizace u zamitnutych pokusu. Export naopak potrebuje
	 *        atomicitu se svou transakci, takze necha FALSE.
	 */
	public function log(
		string $action,
		string $outcome,
		DateTimeImmutable $createdAt,
		?string $correlationId,
		array $actor,
		array $payload,
		bool $detached = false,
	): void {
		$meta = $this->em->getClassMetadata($this->resolveEntityClass());

		$connection = $detached
			? DriverManager::getConnection($this->em->getConnection()->getParams())
			: $this->em->getConnection();

		$userAgent = $actor['userAgent'] ?? null;

		$connection->insert($meta->getTableName(), [
			$meta->getColumnName('action') => $action,
			$meta->getColumnName('outcome') => $outcome,
			// cas jde do DB v UTC - format() bere zonu z objektu
			$meta->getColumnName('createdAt') => $createdAt,
			$meta->getColumnName('correlationId') => $correlationId,
			$meta->getColumnName('createdById') => self::trim($actor['id'] ?? null, 255),
			$meta->getColumnName('createdByLabel') => self::trim($actor['label'] ?? null, 255),
			$meta->getColumnName('createdBy') => $actor['data'] ?: null,
			// utocnik ovlada delku IP i User-Agentu - nikdy nesmi rozbit insert
			$meta->getColumnName('sourceIp') => self::trim($actor['ip'] ?? null, 45),
			$meta->getColumnName('userAgent') => $userAgent,
			$meta->getColumnName('payload') => $payload ?: null,
		], [
			$meta->getColumnName('createdAt') => Types::DATETIME_IMMUTABLE,
			$meta->getColumnName('createdBy') => Types::JSON,
			$meta->getColumnName('payload') => Types::JSON,
		]);
	}

	/** @return class-string */
	private function resolveEntityClass(): string
	{
		foreach ($this->em->getMetadataFactory()->getAllMetadata() as $meta) {
			if ($meta->getReflectionClass()->implementsInterface(AuditLog::class)) {
				return $meta->getName();
			}
		}

		throw new \RuntimeException(
			'Chybi entita implementujici ' . AuditLog::class . '. Vytvor ji pomoci AuditLogTrait a namapuj.'
		);
	}

	private static function trim(?string $value, int $length): ?string
	{
		return $value !== null ? mb_substr($value, 0, $length) : null;
	}
}
