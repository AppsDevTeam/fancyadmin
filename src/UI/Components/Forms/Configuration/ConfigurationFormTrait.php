<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Configuration;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Model\Entities\File;
use ADT\FancyAdmin\Model\FileUploadRules;
use ADT\Forms\Form;
use App\Model\Entities\Account;
use Nette\Http\FileUpload;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * @property Account $entity
 */
trait ConfigurationFormTrait
{
	use EntityManagerInject;

	private const string JSON_VALUE_PREFIX = 'value_';

	public function initForm(Form $form): void
	{
		$form->addText('key', 'Key')
			->setRequired()
			->setDisabled();

		$form->addSelect('type', 'Type', ConfigurationTypeEnum::list())
			->setRequired()
			->setDisabled();

		$form['type']
			->addCondition(Form::Equal, ConfigurationTypeEnum::TYPE_SELECT)
			->toggle('optionsInput')
			->endCondition()
			->addCondition(Form::Equal, ConfigurationTypeEnum::TYPE_FILE)
			->toggle('fileInput')
			->elseCondition()
			->toggle('valueInput');

		$form->addSection(function() use ($form) {
			$form->addTextArea('options', 'Options')
				->setHtmlAttribute('rows', 5);
		}, 'optionsInput');

		$form->addSection(function() use ($form) {
			$form->addUpload('_file', 'File')
				->addRule(Form::MaxFileSize, 'fcadmin.forms.fileUpload.errors.tooLarge', FileUploadRules::MAX_FILE_SIZE)
				->addRule(Form::MimeType, 'fcadmin.forms.fileUpload.errors.mimeTypeMismatch', FileUploadRules::ALLOWED_MIME_TYPES)
				->addConditionOn($form['type'], Form::EQUAL, ConfigurationTypeEnum::TYPE_FILE)
				->setRequired();
		}, 'fileInput');

		$jsonValues = $this->getDecodedJsonValue();

		$form->addSection(function() use ($form, $jsonValues) {
			// pro JSON typ s rozpoznanou strukturou vygenerujeme jednotlivá pole dle klíčů,
			// jinak fallback na původní textarea
			if ($jsonValues !== null) {
				foreach ($jsonValues as $key => $value) {
					$this->addJsonValueField($form, (string) $key, $value);
				}
			} else {
				$form->addTextArea('value', 'Value')
					->setHtmlAttribute('rows', 5);
			}
		}, 'valueInput');

		$form->addSubmit("submit", 'Save');
	}

	public function validateForm(Configuration $entity, array $inputs): void
	{
		// pro JSON skládáme hodnotu z jednotlivých polí (value_*), validní JSON tedy vznikne vždy
		if ($entity->getType() === ConfigurationTypeEnum::TYPE_JSON->value && isset($inputs['value']) && !is_array(json_decode($inputs['value'], true))) {
			$this->form->addError('Input value not contains valid JSON');
		}

		if ($entity->getType() === ConfigurationTypeEnum::TYPE_SELECT->value) {
			$options = json_decode($inputs['options'], true);

			if (!is_array($options)) {
				$this->form->addError('Input options not contains valid JSON');
				return;
			}

			if ($inputs['value'] && !array_key_exists($inputs['value'], $options)) {
				$this->form->addError('Input value not contains valid option from options input.');
			}
		}
	}

	public function processForm(Configuration $entity, array $inputs): void
	{
		if ($entity->getType() === ConfigurationTypeEnum::TYPE_JSON) {
			$original = $this->getDecodedJsonValue();
			if ($original !== null) {
				// poskládáme JSON zpět z jednotlivých polí (value_*) se zachováním původních typů
				$value = [];
				foreach ($original as $key => $originalValue) {
					$value[$key] = $this->castJsonValue($inputs[self::JSON_VALUE_PREFIX . $key] ?? null, $originalValue);
				}
			} else {
				$value = json_decode($inputs['value']);
			}
			$entity->setValue(json_encode($value, JSON_PRETTY_PRINT));
		} elseif ($entity->getType() === ConfigurationTypeEnum::TYPE_FILE) {
			/** @var FileUpload $fileUpload */
			$fileUpload = $inputs['_file'];

			/** @var File $fileEntity */
			$fileEntity = new ($this->em->findEntityClassByInterface(File::class));
			$fileEntity->setTemporaryFile($fileUpload->getTemporaryFile(), $fileUpload->getUntrustedName());

			$entity->setFile($fileEntity);
		}

		$this->_em->flush();
	}

	protected function getEntityClass(): ?string
	{
		return Configuration::class;
	}

	/**
	 * Vrátí dekódovanou JSON hodnotu jako asociativní pole (klíč => hodnota),
	 * nebo null, pokud nejde o JSON typ / hodnotu nelze rozparsovat.
	 *
	 * @return array<string, mixed>|null
	 */
	private function getDecodedJsonValue(): ?array
	{
		$entity = $this->getEntity();
		if (!$entity instanceof Configuration || $entity->getType() !== ConfigurationTypeEnum::TYPE_JSON) {
			return null;
		}

		try {
			// hodnota může mít v DB navíc koncový středník (legacy serializace)
			$rawValue = rtrim(trim((string) $entity->getValue()), ';');

			$decoded = Json::decode($rawValue, Json::FORCE_ARRAY);
			// hodnota mohla být v DB uložena dvojitě zakódovaná (string místo objektu)
			if (is_string($decoded)) {
				$decoded = Json::decode($decoded, Json::FORCE_ARRAY);
			}
		} catch (JsonException) {
			return null;
		}

		return is_array($decoded) ? $decoded : null;
	}

	private function addJsonValueField(Form $form, string $key, mixed $value): void
	{
		$name = self::JSON_VALUE_PREFIX . $key;
		$label = 'fcadmin.presenters.configurations.valueKeys.' . $key;

		if (is_bool($value)) {
			$form->addCheckbox($name, $label)
				->setDefaultValue($value);
		} elseif (is_int($value)) {
			$form->addInteger($name, $label)
				->setDefaultValue($value);
		} else {
			$form->addText($name, $label)
				->setDefaultValue($value === null ? '' : (string) $value);
		}
	}

	/**
	 * Převede hodnotu z formuláře zpět na typ odpovídající původní hodnotě.
	 */
	private function castJsonValue(mixed $input, mixed $original): mixed
	{
		if (is_bool($original)) {
			return (bool) $input;
		}

		if (is_int($original)) {
			return $input === '' || $input === null ? null : (int) $input;
		}

		// původně null a prázdný vstup → zůstává null
		if ($original === null && ($input === '' || $input === null)) {
			return null;
		}

		return $input;
	}
}
