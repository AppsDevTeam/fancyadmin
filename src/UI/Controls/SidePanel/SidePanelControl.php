<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Controls\SidePanel;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\UI\Forms\RenderToStringTrait;
use ADT\FancyAdmin\UI\Presenters\BasePresenter;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\UI\Forms\Base\BaseForm;
use ADT\FancyAdmin\Forms\BaseFormFactoryInterface;
use App\UI\Portal\Components\SidePanels\BaseEntity;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\UI\Control;
use Nette\Application\UI\Template;
use Nette\Http\Url;
use Nette\Localization\Translator;

/**
 * @property BasePresenter $presenter
 */
class SidePanelControl extends Control
{
	use AutowireProperties;
	use AutowireComponentFactories;
	use RenderToStringTrait;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected EntityManager $em;

	protected array $onSuccess = [];
	protected ?string $htmlId = null;
	protected ?BaseEntity $presenterMainEntity = null;
	private $formFactory;
	private BaseEntity|null|\Closure $entity;

	private array $onSetupFormFactory = [];

	private $form = null;

	protected function createTemplate(?string $class = null): Template
	{
		$template = parent::createTemplate();
		$template->setFile(__DIR__ . '/SidePanelControl.latte');
		return $template;
	}

	public function render(): void
	{
		$this->template->render();
	}

	public function _()
	{
		return call_user_func_array([$this->translator, 'translate'], func_get_args());
	}

	protected function applyOnSuccess(BaseForm $form)
	{
		return $form->setOnSuccess(function () use ($form) {
			$this->presenter->redrawControl('sidePanelContainer');
			$this->presenter->redrawControl('container');
			if ($form->form->getEntity()?->getId()) {
				$this->presenter->flashMessageSuccess('app.sidePanels.control.itemEdit');
			} else {
				$this->presenter->flashMessageSuccess('app.sidePanels.control.itemAdd');
			}
			foreach ($this->onSuccess as $onSuccess) {
				$onSuccess();
			}
		});
	}

	public function setOnSuccess(callable $onSuccess): static
	{
		$this->onSuccess[] = $onSuccess;
		return $this;
	}

	public function setPresenterMainEntity(?BaseEntity $entity): static
	{
		$this->presenterMainEntity = $entity;
		return $this;
	}

	public function setFormFactory(BaseFormFactoryInterface $formFactory): static
	{
		$this->formFactory = $formFactory;
		return $this;
	}

	public function setEntity(BaseEntity|null|callable $entity): static
	{
		$this->entity = $entity;
		return $this;
	}

	public function getEntity(): BaseEntity|callable|null
	{
		return $this->entity;
	}

	protected function createComponentForm(): BaseForm
	{
//		V nekterych pripadech je potreba dodat form zvenci napriklad z duvou predavani dalsich parametru na form, pokud
//		byl do sidepanelu takovyto form predan, pak createComponent bude vracet tento predany form
		if ($this->form) {
			return $this->form;
		}

		/** @var BaseForm $form */
		$form = $this->formFactory->create();

		if ($editId = $this->getPresenter()->getParameter('editId')) {
			$this->entity = $this->em->getRepository($form->getEntityClass())->find($editId);
		}

		if ($this->entity) {
			$url = new Url($this->getPresenter()->getHttpRequest()->getUrl());
			$url->setQueryParameter('editId', $this->entity->getId());
			$form->setOnBeforeInitForm(function (Form $form) use ($url) {
				$form->setAction((string) $url);
			});
		}

		$form->setEntity($this->entity);

		foreach ($this->onSetupFormFactory as $onSetupFormFactory) {
			$onSetupFormFactory($form);
		}

		return $this->applyOnSuccess($form);
	}

	public function setForm(BaseForm $form): self
	{
		$this->form = $this->applyOnSuccess($form);
		return $this;
	}

	/**
	 * @param callable(\ADT\DoctrineForms\BaseForm): \ADT\DoctrineForms\BaseForm $onSetupFormFactory
	 * @return self
	 */
	public function onSetupFormFactory(callable $onSetupFormFactory): self
	{
		$this->onSetupFormFactory[] = $onSetupFormFactory;
		return $this;
	}
}
