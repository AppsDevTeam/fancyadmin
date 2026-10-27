<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\AiMcpToken;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\AiMcpToken;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;
use DateTimeImmutable;

/**
 * Formulář MCP tokenu. Token vždy patří přihlášenému uživateli; platnost se volí
 * jen při vytvoření, u existujícího tokenu jde změnit jen název.
 */
trait AiMcpTokenFormTrait
{
	use EntityManagerInject;

	/** Nabídka platnosti ve dnech; null = bez omezení. */
	protected array $aiMcpTokenValidityDays = [30, 90, 365, null];

	protected int $aiMcpTokenDefaultValidityDays = 90;

	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.presenters.aiMcpTokens.form.name')
			->setRequired('fcadmin.presenters.aiMcpTokens.form.errors.nameRequired')
			->setHtmlAttribute('placeholder', 'fcadmin.presenters.aiMcpTokens.form.namePlaceholder');

		if (!$this->getEntity()) {
			$options = [];
			foreach ($this->aiMcpTokenValidityDays as $_days) {
				$options[$_days ?? 0] = $_days === null
					? 'fcadmin.presenters.aiMcpTokens.form.validityUnlimited'
					: $this->getTranslator()->translate('fcadmin.presenters.aiMcpTokens.form.validityDays', ['days' => $_days]);
			}

			// pole nemá vlastnost v entitě, mapper ho přeskočí; zpracuje ho processForm()
			$form->addSelect('validityDays', 'fcadmin.presenters.aiMcpTokens.form.validity', $options)
				->setDefaultValue($this->aiMcpTokenDefaultValidityDays);
		}

		$form->addSubmit('submit', 'fcadmin.presenters.aiMcpTokens.form.submit');
	}

	public function processForm(AiMcpToken $entity, array $values): void
	{
		// token vzniká jen při vytvoření, existující se editací nemění
		$rawToken = null;
		if (!$entity->getTokenHash()) {
			$rawToken = ApiKeyHasher::generateRawKey();
			$entity->setTokenHash(ApiKeyHasher::hash($rawToken))
				->setIdentity($this->_securityUser->getIdentity());

			$days = (int) ($values['validityDays'] ?? 0);
			$entity->setExpiresAt($days > 0 ? new DateTimeImmutable('+' . $days . ' days') : null);
		}

		$this->_em->flush();

		if ($rawToken !== null) {
			// token jde uživateli ukázat jen teď, v databázi zůstává pouze otisk
			$this->getPresenter()->flashMessageInfo(
				$this->getTranslator()->translate('fcadmin.presenters.aiMcpTokens.messages.created', ['token' => $rawToken])
			);
		}
	}

	/** Nový token - doctrine-forms ho po odeslání persistne a předá do processForm(). */
	protected function createEntity(): AiMcpToken
	{
		$class = $this->getEntityClass();

		return new $class();
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(AiMcpToken::class);
	}
}
