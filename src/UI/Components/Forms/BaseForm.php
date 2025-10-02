<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait;
use ADT\Forms\BootstrapFormRenderer;
use App\Model\Doctrine\EntityManager;
use App\Model\Security\SecurityUser;
use App\Model\Translator;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;

/**
 * @template TEntity of object
 */
abstract class BaseForm extends \ADT\DoctrineForms\BaseForm
{
	use AutowireProperties;
	use AutowireComponentFactories;
	use BaseFormTrait;
	use TranslatorInject;

	abstract protected function getEntityClass(): ?string;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected SecurityUser $securityUser;

	protected function createComponentForm(): EntityForm
	{
		$form = new EntityForm();
		$form->setTranslator($this->_translator);
		$form->setEntityManager($this->em);
		$form->setRenderer(new BootstrapFormRenderer($form));
		return $form;
	}

	public function addIsActive(EntityForm $form): void
	{
		$form->addCheckbox('isActive', 'app.forms.device.labels.isActive')
			->setDefaultValue(true);
	}

	protected function getEntityManager(): EntityManager
	{
		return $this->em;
	}

	protected function getTranslator(): Translator
	{
		return $this->translator;
	}

	protected function createEntity()
	{
		if ($this->getEntityClass()) {
			return new ($this->getEntityClass())();
		}

		return null;
	}
}
