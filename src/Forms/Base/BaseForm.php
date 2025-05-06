<?php

namespace ADT\FancyAdmin\Forms\Base;

use ADT\DoctrineForms\Form;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Presenters\BasePresenter;
use Contributte\Translation\Translator;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Http\Url;

/**
 * @property BasePresenter $presenter
 */
abstract class BaseForm extends \ADT\DoctrineForms\BaseForm
{
	use AutowireProperties;
	use AutowireComponentFactories;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected SecurityUser $securityUser;

	public bool $emptyHiddenToggleControls = true;

	protected ?BaseEntity $presenterMainEntity = null;
	protected ?string $modalHtmlId = null;

	public function __construct()
	{
		$this->setOnBeforeInitForm(function (Form $form) {
			$form->setEntityManager($this->em);
		});
		parent::__construct();
	}

	protected function createComponentForm(): EntityForm
	{
		$form = new EntityForm();
		$form->setTranslator($this->translator);
		$form->setRenderer(
			(new FormRenderer($form))
				->setModalHtmlId($this->modalHtmlId)
		);
		return $form;
	}

	public function setPresenterMainEntity(?BaseEntity $entity): static
	{
		$this->presenterMainEntity = $entity;
		return $this;
	}

	public function setModalHtmlId(?string $htmlId): static
	{
		$this->modalHtmlId = $htmlId;
		return $this;
	}
}
