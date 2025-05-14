<?php

namespace ADT\FancyAdmin\UI\Forms\Base;

use ADT\DoctrineForms\Form;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use \ADT\DoctrineAuthenticator\SecurityUser;
use ADT\FancyAdmin\UI\Presenters\BasePresenter;
use ADT\FancyAdmin\UI\Forms\Base\EntityForm;
use ADT\FancyAdmin\UI\Forms\Base\FormRenderer;
use Contributte\Translation\Translator;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;

/**
 * @property BasePresenter $presenter
 */
abstract class BaseForm extends \ADT\DoctrineForms\BaseForm
{
	use AutowireProperties;
	use AutowireComponentFactories;

	public bool $emptyHiddenToggleControls = true;

	protected ?BaseEntity $presenterMainEntity = null;
	protected ?string $modalHtmlId = null;

	public function __construct(
		protected EntityManager $em,
		protected Translator $translator,
		protected SecurityUser $securityUser
	)
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

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->em;
	}
}
