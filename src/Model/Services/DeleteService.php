<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineForms\Entity;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class DeleteService
{
	public function __construct(
		readonly protected EntityManagerInterface $em,
	) {
	}

	public function isPossibleToDeleteEntity(Entity $entity): bool
	{
		$bool = true;
		$this->em->beginTransaction();

		try {
			$this->lowLevelDelete($entity);
		} catch (ForeignKeyConstraintViolationException $e) {
			$bool = false;
		}

		$this->em->rollback();

		return $bool;
	}

	public function delete(Entity $entity): void
	{
		$this->em->remove($entity);
		$this->em->flush();
	}

	public function lowLevelDelete(Entity $entity): void
	{
		$class = get_class($entity);
		$this->em->createQueryBuilder()
			->delete()
			->from($class, 'e')
			->andWhere('e.id = :entityId')
			->setParameter('entityId', $entity->getId())
			->getQuery()
			->execute();
	}
}
