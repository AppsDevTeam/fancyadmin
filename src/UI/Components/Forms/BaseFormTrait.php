<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\Forms\BootstrapFormRenderer;
use App\UI\Portal\Components\Forms\Base\EntityForm;

trait BaseFormTrait
{
	use ControlTrait;
	use EntityManagerInject;
	use TranslatorInject;
	use AccountQueryFactoryInject;

	protected bool $disableAccountInput = false;

	/**
	 * @throws \ReflectionException
	 */
	public function onBeforeInitForm(Form $form): void
	{
		if ($this->getEntityClass() && !$this->disableAccountInput) {
			$refClass = new \ReflectionClass($this->getEntityClass());

			if ($refClass->hasProperty('account')) {
				$accountyQuery = $this->_accountQueryFactory->create();
				if ($this->securityUser->getIdentity()->getSelectedAccount()) {
					$accountyQuery->byIdOrParentId($this->securityUser->getIdentity()->getSelectedAccount());
				}

				if ($accountyQuery->count() > 1) {
					$pairs = [];
					foreach ($accountyQuery->orderBy(['parent' => 'ASC', 'name' => 'ASC'])->fetch() as $_account) {
						if ($_account->getParent()) {
							$pairs[$_account->getId()] = '-- ' . $_account->getName();
						} else {
							$pairs[$_account->getId()] = $_account->getName();
						}
					}

					$form->addSelect('account', 'Účet', $pairs)  // TODO translate
						->setPrompt('---')
						->setRequired();
				}
			}
		}
	}

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->_translator);
		$form->setEntityManager($this->_em);
		$form->setRenderer(new BootstrapFormRenderer($form));
		$form->addProtection('fcadmin.forms.errors.csrf');
		return $form;
	}

	public function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}

	public function getRedirect($entity = null): ?array
	{
		return null;
	}

	public static function getDefaultTemplateFile(): string
	{
		return __DIR__ . '/BaseForm.latte';
	}
}
