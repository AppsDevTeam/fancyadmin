<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Configuration;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Model\Entities\File;
use ADT\Forms\Form;
use App\Model\Entities\Account;
use Nette\Http\FileUpload;

/**
 * @property Account $entity
 */
trait ConfigurationFormTrait
{
	use EntityManagerInject;

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
				->addConditionOn($form['type'], Form::EQUAL, ConfigurationTypeEnum::TYPE_FILE)
				->setRequired();
		}, 'fileInput');

		$form->addSection(function() use ($form) {
			$form->addTextArea('value', 'Value')
				->setHtmlAttribute('rows', 5);
		}, 'valueInput');

		$form->addSubmit("submit", 'Save');
	}

	public function validateForm(Configuration $entity, array $inputs): void
	{
		if ($entity->getType() === ConfigurationTypeEnum::TYPE_JSON->value && !is_array(json_decode($inputs['value'], true))) {
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
		if ($entity->getType() === ConfigurationTypeEnum::TYPE_JSON->value) {
			$value = json_decode($inputs['value']);
			$entity->setValue(json_encode($value, JSON_PRETTY_PRINT));
		} elseif ($entity->getType() === ConfigurationTypeEnum::TYPE_FILE->value) {
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
}
