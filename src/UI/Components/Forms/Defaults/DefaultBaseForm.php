<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\UI\Components\Forms\BaseForm;
use ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait;
use ADT\DoctrineComponents\EntityManager;

/**
 * Základ výchozích formulářů balíčku, které DI rozšíření zaregistruje, když projekt vlastní
 * formulář (továrnu) nemá. Závislosti dostane přes inject metody BaseFormTrait.
 */
abstract class DefaultBaseForm extends \ADT\DoctrineForms\BaseForm implements BaseForm
{
	use BaseFormTrait {
		createComponentForm as private createComponentFormTrait;
	}

	protected function getEntityManager(): EntityManager
	{
		return $this->_em;
	}

	protected function createComponentForm(): Form
	{
		$form = $this->createComponentFormTrait();
		$form->setRenderer(new SubmitFormRenderer($form));
		return $form;
	}
}
