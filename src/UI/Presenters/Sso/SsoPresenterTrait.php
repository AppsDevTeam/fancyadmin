<?php

namespace ADT\FancyAdmin\UI\Presenters\Sso;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SsoFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\SsoQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Grids\Sso\SsoGrid;
use ADT\FancyAdmin\UI\Components\Grids\Sso\SsoGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

trait SsoPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use FancyAdminInject;
	use SsoFormFactoryInject;
	use SsoQueryFactoryInject;

	protected function startup(): void
	{
		parent::startup();
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			$this->error('Keycloak is not enabled', 404);
		}
	}

	public function actionDefault(?Sso $sso = null): void
	{
		if ($sso) {
			$this->entity = $sso;
		}

		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function handleEdit(Sso $sso): void
	{
		$this->entity = $sso;
		$this->redrawSidePanel();
	}

	public function handleNew(): void
	{
		$this->entity = null;
		$this->redrawSidePanel();
	}

	public function createComponentSsoGrid(SsoGridFactory $factory): SsoGrid
	{
		return $factory->create();
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_ssoFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_ssoQueryFactory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}
}
