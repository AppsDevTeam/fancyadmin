<?php

namespace ADT\FancyAdmin\UI\Forms;

use ADT\DoctrineForms\Form;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use \ADT\DoctrineAuthenticator\SecurityUser;
use ADT\FancyAdmin\UI\Presenters\BasePresenter;
use ADT\FancyAdmin\UI\Forms\Base\EntityForm;
use ADT\FancyAdmin\UI\Forms\Base\FormRenderer;
use ADT\Forms\BootstrapFormRenderer;
use Contributte\Translation\Translator;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;

abstract class BaseForm extends \ADT\DoctrineForms\BaseForm
{
	use AutowireProperties;
	use AutowireComponentFactories;

	#[Autowire]
	protected EntityManagerInterface $em;

	#[Autowire]
	protected Translator $translator;

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->setEntityManager($this->em);
		$form->setRenderer(new BootstrapFormRenderer($form));
		return $form;
	}

	protected function getEntityManager(): EntityManagerInterface
	{
		return $this->em;
	}
}
