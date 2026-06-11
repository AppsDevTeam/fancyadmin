<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Configuration;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

trait ConfigurationGridTrait
{
	use Editable;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'fcadmin.presenters.configurations.grid.name');
		$grid->addColumnText('value', 'fcadmin.presenters.configurations.grid.value')
			->setRenderer(function(Configuration $configuration) {
				if ($configuration->getType() === ConfigurationTypeEnum::TYPE_JSON) {
					try {
						// hodnota může mít v DB navíc koncový středník (legacy serializace)
						$rawValue = rtrim(trim((string) $configuration->getValue()), ';');

						$decoded = Json::decode($rawValue, Json::FORCE_ARRAY);
						// hodnota mohla být v DB uložena dvojitě zakódovaná (string místo objektu)
						if (is_string($decoded)) {
							$decoded = Json::decode($decoded, Json::FORCE_ARRAY);
						}

						if (is_array($decoded)) {
							return $this->renderConfigurationValueList($decoded);
						}

						return $configuration->getValue();
					} catch (JsonException) {
						return $configuration->getValue();
					}
				} elseif ($configuration->getType() === ConfigurationTypeEnum::TYPE_FILE) {
					return $configuration->getFile()->getUrl();
				}

				if ($this->isSensitiveConfiguration($configuration->getKey())) {
					return $this->maskSensitiveValue((string) $configuration->getValue());
				}

				return $configuration->getValue();
			});
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ConfigurationQueryFactory::class;
	}

	private function isSensitiveConfiguration(string $key): bool
	{
		foreach (['key', 'secret', 'token', 'password'] as $needle) {
			if (str_contains(strtolower($key), $needle)) {
				return true;
			}
		}

		return false;
	}

	private function maskSensitiveValue(string $value): string
	{
		$length = mb_strlen($value);

		// krátké hodnoty schováme celé
		if ($length <= 8) {
			return str_repeat('•', max($length, 1));
		}

		$visible = 4;

		return mb_substr($value, 0, $visible)
			. str_repeat('•', max($length - 2 * $visible, 1))
			. mb_substr($value, -$visible);
	}

	private function renderConfigurationValueList(array $values): Html
	{
		$container = Html::el();

		foreach ($values as $key => $value) {
			$container->addHtml(
				Html::el('div')
					->addHtml(Html::el('strong')->setText($this->translateConfigurationValueKey((string) $key) . ': '))
					->addText($this->formatConfigurationValue($value))
			);
		}

		return $container;
	}

	private function translateConfigurationValueKey(string $key): string
	{
		$translationKey = 'fcadmin.presenters.configurations.valueKeys.' . $key;
		$translated = $this->getTranslator()->translate($translationKey);

		return $translated === $translationKey ? $key : $translated;
	}

	private function formatConfigurationValue(mixed $value): string
	{
		if (is_bool($value)) {
			return $this->getTranslator()->translate($value ? 'fcadmin.appGeneral.model.filters.yes' : 'fcadmin.appGeneral.model.filters.no');
		}

		if ($value === null) {
			return '—';
		}

		if (is_array($value)) {
			return Json::encode($value);
		}

		return (string) $value;
	}
}
