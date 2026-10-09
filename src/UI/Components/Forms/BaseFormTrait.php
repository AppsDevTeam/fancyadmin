<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Entities\Account;
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
	protected bool $csrfProtection = true;

	abstract protected function getEntityClass(): ?string;

	/**
	 * @throws \ReflectionException
	 */
	public function onBeforeInitForm(Form $form): void
	{
		$this->registerCsrfProtection();

		if ($this->getEntityClass() && !$this->disableAccountInput) {
			$refClass = new \ReflectionClass($this->getEntityClass());

			if ($refClass->hasProperty('account')) {
				// Bez účtu v URL jsme v backoffice - AuthPresenterTrait::startup() tam
				// identitě vybraný účet nuluje. Výběr by nabízel všechny účty v systému,
				// což u backoffice záznamu nedává smysl; účet doplní AccountFieldListener.
				$selectedAccount = $this->securityUser->getIdentity()?->getSelectedAccount();
				if (!$selectedAccount) {
					return;
				}

				$pairs = [];
				$accounts = $this->_accountQueryFactory->create()
					->byIdOrParentId($selectedAccount)
					->orderBy(['parent' => 'ASC', 'name' => 'ASC'])
					->fetch();
				foreach ($accounts as $_account) {
					$pairs[$_account->getId()] = $this->getAccountLabel($_account);
				}

				// Účet editovaného záznamu musí být v nabídce vždy, i když ho dotaz nevrátil.
				// AccountQuery filtruje isActive, kdežto account filtr dotazů (DefaultFilters,
				// `account = :account OR account.parent = :account`) ne - po deaktivaci podúčtu
				// tak jeho záznamy grid dál nabízí k editaci, ale formulář by je nedokázal
				// reprezentovat: pole by buď zmizelo úplně (zbyl by jediný účet) a nabídky
				// stavěné z vybraného účtu by sáhly vedle, nebo by Nette při mapování entity
				// spadlo na "Value '...' are out of allowed set".
				$entityAccount = $this->getEntityAccount();
				if ($entityAccount && !isset($pairs[$entityAccount->getId()])) {
					$pairs[$entityAccount->getId()] = $this->getAccountLabel($entityAccount);
				}

				if (count($pairs) > 1) {
					$form->addSelect('account', 'fcadmin.forms.user.labels.account', $pairs)
						->setPrompt('---')
						->setRequired();
				}
			}
		}
	}

	/**
	 * Účet editovaného záznamu. Nový záznam ho ještě nemá, doplní ho AccountFieldListener.
	 *
	 * Editovaný záznam drží jen formulář nad entitou ({@see \ADT\DoctrineForms\BaseForm}).
	 * Trait používají i formuláře bez ní ({@see \ADT\Forms\BaseForm}), kde getEntity()
	 * neexistuje - proto se na ni ptáme, místo abychom ji předepsali jako abstract.
	 */
	private function getEntityAccount(): ?Account
	{
		if (!method_exists($this, 'getEntity')) {
			return null;
		}

		$entity = $this->getEntity();

		if (!$entity || !method_exists($entity, 'getAccount')) {
			return null;
		}

		$account = $entity->getAccount();

		return $account instanceof Account ? $account : null;
	}

	private function getAccountLabel(Account $account): string
	{
		return ($account->getParent() ? '-- ' : '') . $account->getName();
	}

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->_translator);
		$form->setEntityManager($this->_em);
		$form->setRenderer(new BootstrapFormRenderer($form));
		$this->registerCsrfProtection();
		return $form;
	}

	/**
	 * Ochrana se vesi az na `onAfterInitForm`, protoze `Form::setMethod()` snese jen prazdny
	 * formular: prvni prvek v nem spusti pri pripojeni k prezenteru `isSubmitted()`, tim se
	 * nactou HTTP data a `setMethod()` uz vyhodi vyjimku. Pridana rovnou pri sestaveni
	 * formulare proto rozbila kazdy formular, ktery si metodu meni.
	 *
	 * Registruje se na dvou mistech schvalne. Projekt casto prepisuje `createComponentForm()`
	 * (stavi si vlastni Form), kdezto `onBeforeInitForm()` prepisuje malokdy - a naopak.
	 * Samotne pridani ochrany je proto napsane tak, aby druhe volani uz nic neudelalo.
	 */
	protected function registerCsrfProtection(): void
	{
		$this->onAfterInitForm[] = function (Form $form): void {
			$this->addCsrfProtection($form);
		};
	}

	/**
	 * Vynechava se ve dvou pripadech:
	 *
	 * - Formular odesilany GETem (filtry) - token by skoncil v adrese, tedy presne tam, odkud
	 *   ho {@see \ADT\FancyAdmin\Core\SignalCsrfRouteList} uklizi. Takovy formular dal chrani
	 *   token signalu (`_sec`).
	 * - Neprihlaseny uzivatel - `addProtection()` uklada token do relace, takze by radek
	 *   v tabulce sessions dostal kazdy navstevnik prihlasovaci stranky. Presne temu se
	 *   vyhyba {@see \ADT\FancyAdmin\UI\Presenters\SignalCsrfProtection} i ReturnPath.
	 */
	protected function addCsrfProtection(Form $form): void
	{
		if (
			!$this->csrfProtection
			|| !$form->isMethod('post')
			|| !$this->securityUser->isLoggedIn()
			|| $form->getComponent(Form::ProtectorId, throw: false) !== null
		) {
			return;
		}

		$form->addProtection('fcadmin.forms.errors.csrf');
	}

	public function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}

	public function getRedirect($entity = null): ?array
	{
		return null;
	}

	public function getSnippetsToRedraw(): array
	{
		return [];
	}

	public static function getDefaultTemplateFile(): string
	{
		return __DIR__ . '/BaseForm.latte';
	}
}
