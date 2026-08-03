<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Passkey;

use ADT\Forms\Form;

/**
 * Formulář pro přidání passkey v side panelu na Account stránce.
 *
 * WebAuthn ceremony řídí JS komponenta (data-adt-fancyadmin-passkey-form), která volá
 * signály passkeyRegisterArgs / passkeyRegisterVerify na presenteru — formulář se
 * nikdy neodesílá standardně, proto nemá vazbu na entitu.
 */
trait PasskeyFormTrait
{
	public function initForm(Form $form): void
	{
		$form->addText('name', 'fcadmin.passkeys.form.name')
			->setHtmlAttribute('maxlength', 64)
			->setHtmlAttribute('placeholder', 'fcadmin.passkeys.form.namePlaceholder')
			->setRequired('fcadmin.passkeys.form.errors.nameRequired')
			->addRule($form::MaxLength, null, 64);

		$presenter = $this->getPresenter();
		$form->getElementPrototype()->setAttribute('data-adt-fancyadmin-passkey-form', true);
		$form->getElementPrototype()->setAttribute('data-passkey-register-args-url', $presenter->link('passkeyRegisterArgs!'));
		$form->getElementPrototype()->setAttribute('data-passkey-register-verify-url', $presenter->link('passkeyRegisterVerify!'));
		$form->getElementPrototype()->setAttribute('data-passkey-error-unsupported', $this->getTranslator()->translate('fcadmin.passkeys.errors.unsupportedBrowser'));
		$form->getElementPrototype()->setAttribute('data-passkey-error-failed', $this->getTranslator()->translate('fcadmin.passkeys.errors.registrationFailed'));
		$form->getElementPrototype()->setAttribute('data-passkey-error-name-required', $this->getTranslator()->translate('fcadmin.passkeys.form.errors.nameRequired'));

		$form->addSubmit('register', 'fcadmin.passkeys.form.register')
			->setHtmlAttribute('data-passkey-register-button');
	}

	public function validateForm(array $values, Form $form): void
	{
		// Formulář se odesílá jen přes JS signály; přímý submit = prohlížeč bez WebAuthn/JS
		$form->addError('fcadmin.passkeys.errors.unsupportedBrowser');
	}

	protected function getEntityClass(): ?string
	{
		// Entita nevzniká z formuláře — vytváří ji PasskeyService::processRegistration()
		return null;
	}
}
