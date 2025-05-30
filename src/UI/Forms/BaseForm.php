<?php

namespace ADT\FancyAdmin\UI\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\Model\Security\SecurityUserInterface;
use ADT\Forms\BootstrapFormRenderer;
use Contributte\Translation\Translator;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelSize;

abstract class BaseForm extends \ADT\DoctrineForms\BaseForm
{
	use AutowireProperties;
	use AutowireComponentFactories;

	#[Autowire]
	protected EntityManagerInterface $em;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected SecurityUserInterface $securityUser;

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

	public function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}

	protected function initEntity()
	{
		if (!$this->getEntityClass()) {
			return null;
		}
		$entity = new ($this->getEntityClass());
		$this->em->persist($entity);
		return $entity;
	}

	public function getRedirect($entity = null): ?array
	{
		return null;
	}
}
