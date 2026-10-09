<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\ChangeLog;

use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineLoggable\ChangeSet\Redacted;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\FancyAdmin\Model\Attributes\Label;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Factories\ChangeLogQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use ReflectionClass;

trait ChangeLogGridTrait
{
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;

		$grid->setDefaultSort(['createdAt' => 'DESC']);

		// v logu se hleda skoro vzdycky "co se delo v ten a ten den"
		$grid->addColumnDateTime('createdAt', 'fcadmin.grids.changeLog.createdAt')
			->setFilterDateRange();

		$grid->addColumnText('action', 'fcadmin.grids.changeLog.action')
			->setRenderer(function (ChangeLog $changeLog) {
				return $this->getTranslator()->translate('fcadmin.grids.changeLog.actions.' . $changeLog->getAction());
			});

		$grid->addColumnText('objectClass', 'fcadmin.grids.changeLog.objectClass')
			->setRenderer(function (ChangeLog $changeLog) {
				return $this->resolveEntityLabel($changeLog->getObjectClass());
			});

		$grid->addColumnText('objectId', 'fcadmin.grids.changeLog.objectId')
			->setFilterText();

		$grid->addColumnText('identityId', 'fcadmin.grids.changeLog.identity')
			->setRenderer(function (ChangeLog $changeLog) {
				if ($changeLog->getIdentityId() === null) {
					return '—';
				}
				$identityClass = $this->getEntityManager()->findEntityClassByInterface(Identity::class);
				$identity = $this->getEntityManager()->getRepository($identityClass)->find($changeLog->getIdentityId());
				return $identity?->getEmail() ?? '#' . $changeLog->getIdentityId();
			});

		$grid->addColumnText('changeSet', 'fcadmin.grids.changeLog.changeSet')
			->setRenderer(function (ChangeLog $changeLog) {
				return $this->renderChangeSet($changeLog);
			});

		$this->addSearchFilter($grid, ['objectClass', 'objectId']);

		$grid->addFilterSelect('action', 'fcadmin.grids.changeLog.action', [
			'create' => $this->getTranslator()->translate('fcadmin.grids.changeLog.actions.create'),
			'edit' => $this->getTranslator()->translate('fcadmin.grids.changeLog.actions.edit'),
			'delete' => $this->getTranslator()->translate('fcadmin.grids.changeLog.actions.delete'),
		])->setPrompt('—');

		$grid->addAdvancedFilteredSearch();
		$grid->addExportExcel('Export', 'change-logs.xlsx');
	}

	/**
	 * Properties, jejichž hodnotu grid nesmí vypsat - hashe hesel, tokeny, API klíče,
	 * certifikáty. Že se property změnila, v logu zůstane; maskuje se stará a nová hodnota.
	 *
	 * Ve výchozím stavu prázdné: co je v daném projektu tajné, ví jen ten projekt.
	 * Přepiš v komponentě, která traitu používá.
	 *
	 * @return list<string>
	 */
	protected function getMaskedProperties(): array
	{
		return [];
	}

	public function getIsMaskedProperty(string $property): bool
	{
		return in_array($property, $this->getMaskedProperties(), true);
	}

	protected function renderChangeSet(ChangeLog $changeLog): Html
	{
		$labels = $this->resolvePropertyLabels($changeLog->getObjectClass());
		$container = Html::el('div')->setAttribute('style', 'font-size: 0.85em;');

		foreach ($changeLog->getChangeSet()->getChangedProperties() as $propertyChangeSet) {
			if (!$propertyChangeSet->isChanged()) {
				continue;
			}

			$propertyName = $propertyChangeSet->getName();
			$row = Html::el('div')->setAttribute('style', 'white-space: nowrap;');
			$row->addHtml(Html::el('span')->setAttribute('class', 'text-muted')->setText(($labels[$propertyName] ?? $propertyName) . ' '));

			// Redacted = hodnota se do logu vubec nezapsala (LoggableProperty::$withValue),
			// maskovani = zapsala, ale grid ji vypsat nesmi. Navenek je to totez.
			if ($propertyChangeSet instanceof Redacted || $this->getIsMaskedProperty($propertyName)) {
				$row->addText($this->getTranslator()->translate('fcadmin.grids.changeLog.masked'));
			} elseif ($propertyChangeSet instanceof Scalar) {
				$row->addText($this->formatValue($propertyChangeSet->getOld()) . ' → ' . $this->formatValue($propertyChangeSet->getNew()));
			} elseif ($propertyChangeSet instanceof ToOne) {
				if ($this->isFileChange($propertyChangeSet)) {
					$row->addText($this->formatFileChange($propertyChangeSet));
				} else {
					$row->addText($this->formatIdentification($propertyChangeSet->getOld()) . ' → ' . $this->formatIdentification($propertyChangeSet->getNew()));
				}
			} elseif ($propertyChangeSet instanceof ToMany) {
				$removed = array_map(fn($id) => '− ' . $this->formatIdentification($id), $propertyChangeSet->getRemoved());
				$added = array_map(fn($id) => '+ ' . $this->formatIdentification($id), $propertyChangeSet->getAdded());
				$row->addText(implode(', ', array_merge($removed, $added)) ?: '—');
			} else {
				// neznamy typ zmeny - vypsat samotny popisek bez hodnoty nema smysl
				continue;
			}

			$container->addHtml($row);
		}

		return $container;
	}

	protected function resolveEntityLabel(string $entityClass): string
	{
		static $cache = [];

		if (!isset($cache[$entityClass])) {
			$array = explode('\\', $entityClass);
			$fallback = end($array);

			if (class_exists($entityClass)) {
				$reflection = new ReflectionClass($entityClass);
				$attributes = $reflection->getAttributes(Label::class);
				if ($attributes) {
					$cache[$entityClass] = $this->getTranslator()->translate($attributes[0]->newInstance()->translationKey);
				}
			}

			$cache[$entityClass] ??= $fallback;
		}

		return $cache[$entityClass];
	}

	protected function resolvePropertyLabels(string $entityClass): array
	{
		static $cache = [];

		if (isset($cache[$entityClass])) {
			return $cache[$entityClass];
		}

		$labels = [];

		if (!class_exists($entityClass)) {
			return $labels;
		}

		$reflection = new ReflectionClass($entityClass);
		foreach ($reflection->getProperties() as $property) {
			$attributes = $property->getAttributes(Label::class);
			if ($attributes) {
				$label = $attributes[0]->newInstance();
				$labels[$property->getName()] = $this->getTranslator()->translate($label->translationKey);
			}
		}

		$cache[$entityClass] = $labels;
		return $labels;
	}

	protected function formatValue(mixed $value): string
	{
		if ($value === null) {
			return 'NULL';
		}

		if ($value instanceof \DateTimeInterface) {
			return $value->format('j. n. Y G:i');
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if (is_scalar($value)) {
			return Strings::truncate((string) $value, 200);
		}

		return '?' . gettype($value);
	}

	protected function formatIdentification($id): string
	{
		if ($id === null) {
			return 'NULL';
		}

		if ($id->getIdentification()) {
			$parts = [];
			foreach ($id->getIdentification() as $key => $value) {
				$parts[] = "$value";
			}
			return implode(', ', $parts);
		}

		$class = explode('\\', $id->getClass());
		return end($class) . ' #' . $id->getId();
	}

	protected function isFileChange(ToOne $toOne): bool
	{
		$old = $toOne->getOld();
		$new = $toOne->getNew();
		$class = $new?->getClass() ?? $old?->getClass();
		if ($class === null) {
			return false;
		}
		return is_a($class, \ADT\Files\Entities\File::class, true);
	}

	protected function formatFileChange(ToOne $toOne): string
	{
		$old = $toOne->getOld();
		$new = $toOne->getNew();

		if ($old === null && $new !== null) {
			return $this->getTranslator()->translate('fcadmin.grids.changeLog.file.uploaded');
		}
		if ($old !== null && $new === null) {
			return $this->getTranslator()->translate('fcadmin.grids.changeLog.file.removed');
		}
		return $this->getTranslator()->translate('fcadmin.grids.changeLog.file.changed');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ChangeLogQueryFactory::class;
	}
}
