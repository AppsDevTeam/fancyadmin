<?php

namespace ADT\FancyAdmin\Presenters;

use ADT\FancyAdmin\Model\Latte\RedrawSidePanel;

use ADT\FancyAdmin\Model\Queries\Factories\CompanyTransactionQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\GridFilterQueryFactory;
use ADT\FancyAdmin\Forms\GridFilter\GridFilterFormFactory;
use App\UI\Portal\Components\Panels\GridFilterPanelControl\GridFilterPanelControlFactory;
use ADT\FancyAdmin\SidePanels\SidePanelControl;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\Json;
use ReflectionClass;
use ReflectionException;

abstract class AuthPresenter extends BasePresenter
{
	use RedrawSidePanel;

	#[Autowire]
	protected GridFilterFormFactory $gridFilterFormFactory;

	#[Autowire]
	protected GridFilterQueryFactory $gridFilterQueryFactory;

	#[Persistent]
	public ?string $gridFilterClass = null;

	#[Persistent]
	public array $gridFilterParameters = [];

	protected ?BaseEntity $entity = null;

	/**
	 * @throws InvalidLinkException
	 */
	protected function startup(): void
	{
		parent::startup();

		if (!$this->isLogged()) {
			$this->redirect(':Portal:Sign:in');
		}

		// TODO delame kvuli ublaboo datagridu ktery potrebuje sessionu uz pri vykresleni
		$this->getSession()->start();

		$this->primaryTemplate = true;
	}

	public function beforeRender(): void
	{
		parent::beforeRender();
	}

	/**
	 * @throws ReflectionException
	 * @throws ForbiddenRequestException
	 */
	public function checkRequirements($element): void
	{
		parent::checkRequirements($element);
		if ($this->securityUser->isLoggedIn()) {
			$this->validateSecurityAttributes();
		}
	}

	/**
	 * @throws ReflectionException
	 * @throws ForbiddenRequestException
	 */
	private function validateSecurityAttributes(): void
	{
		$reflection = new ReflectionClass($this->getPresenter()::class);
		$reflectionMethod = $reflection->getMethod(static::ActionKey . ucfirst($this->getAction()));

		foreach ($reflectionMethod->getAttributes() as $attribute) {
			if ($attribute->getName() === SecurityCheckAttribute::class) {
				$attributeInstance = $attribute->newInstance();
				$aclResourceName = $attributeInstance->getResourceName();

				if (!$this->getUser()->isAllowed($aclResourceName)) {
					throw new ForbiddenRequestException();
				}
			}
		}
	}

	public function handleEditGridFilter(): void
	{
		$this->redrawSidePanel('gridFilter');
	}

	public function handleRemoveGridFilter(): void
	{
	}

	public function afterRender(): void
	{
		if (!$this->isControlInvalid()) {
			$this->redrawControl('title');
			$this->redrawControl('sideMenu');
			if ($this->getParameter('redrawBody')) {
				$this->redrawControl('body');
			} else {
				$this->redrawControl('container');
				if ($this->getFlashSession()->get('flash')) {
					$this->redrawControl('flashes');
				}
			}
		}
	}

	public function createComponentGridFilterSidePanel(GridFilterPanelControlFactory $factory): SidePanelControl
	{
		if ($this->getParameter('columns')) {
			$this->gridFilterParameters = Json::decode($this->getParameter('columns'), forceArrays: true);
		}
		if ($this->getParameter('gridFilterClass')) {
			$this->gridFilterClass = $this->getParameter('gridFilterClass');
		}

		$gridFilter = $this->getParameter('editId')
			? $this->gridFilterQueryFactory->create()->byId($this->getParameter('editId'))->fetchOneOrNull()
			: (new GridFilter())
				->setGrid($this->gridFilterClass)
				->setCompany($this->securityUser->getIdentity()->getFilteredCompany());

		$form = $this->gridFilterFormFactory->create()
			->setEntity($gridFilter)
			->setFilterList($this->gridFilterParameters);

		return $factory->create()
			->setEntity($gridFilter)
			->setForm($form);
	}
}
