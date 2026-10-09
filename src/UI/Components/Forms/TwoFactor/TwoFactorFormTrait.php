<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\TwoFactor;

use ADT\DoctrineAuthenticator\OTP\OnetimeToken;
use ADT\DoctrineAuthenticator\OTP\OnetimeTokenTypeEnum;
use ADT\DoctrineAuthenticator\OTP\TooManyTokenAttemptsException;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenServiceInject;
use ADT\FancyAdmin\DI\Injects\PasskeyServiceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Components\Forms\PasskeyLoginTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;

/**
 * Druhý krok přihlášení identity s vynuceným 2FA (README 19.9). Má dvě obrazovky: výběr
 * způsobu ověření a po odeslání kódu jeho zadání. Rozhoduje mezi nimi čas odeslání kódu
 * v session, který zároveň hlídá prodlevu mezi odesláními.
 *
 * Kód se neověřuje přes Authenticator::authenticate(): OnetimeTokenAuthenticator bere druhý
 * argument jako heslo *nebo* OTP token, takže by stačilo do pole pro kód napsat heslo
 * a druhý faktor by se obešel.
 */
trait TwoFactorFormTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
	use PasskeyLoginTrait;
	use FancyAdminInject;
	use SecurityUserInject;
	use IdentityQueryFactoryInject;
	use PasskeyServiceInject;
	use TranslatorInject;
	use MailerInject;
	use OnetimeTokenServiceInject;

	/** Stejná jako expirace čekajícího stavu v PasskeySessionSection */
	private const int CODE_LIFETIME_MINUTES = 10;

	private const int CODE_LENGTH = 6;

	private const int MAX_CODE_ATTEMPTS = 5;

	private const int RESEND_DELAY_SECONDS = 60;

	private Identity $_identity;

	private OnetimeToken $_onetimeToken;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$identity = $this->findPendingIdentity();
		$isCodeSent = $this->_passkeyService->getTwoFactorCodeSentAt() !== null;

		// Prázdná sekce musí být první, jinak se nevykreslí nad inputem (ADT\Forms sekcím
		// počítá pozici od té předchozí)
		$form->addSection(name: $isCodeSent ? 'codeSent' : 'methods');

		if ($isCodeSent) {
			$form->addSection(function () use ($form) {
				$form->addText('code')
					->setHtmlAttribute('placeholder', 'fcadmin.passkeys.twoFactor.codeLabel')
					->setHtmlAttribute('autocomplete', 'one-time-code')
					->setHtmlAttribute('autocapitalize', 'characters')
					->setHtmlAttribute('maxlength', self::CODE_LENGTH)
					->setRequired('fcadmin.passkeys.twoFactor.codeRequired');
			}, 'inputsWrap');

			$form->addSubmit('submit', 'fcadmin.passkeys.twoFactor.submit')
				->getControlPrototype()->class[] = 'w-100';

			// Sekce přidaná až za submitem se vykreslí pod ním
			$form->addSection(name: 'back');
		}

		$template = $this->getTemplate();
		// Identita bez klíče se jím přihlásit nemůže, tlačítko by jen vracelo chybu
		$template->isPasskeyLoginAvailable = $identity !== null && $this->_passkeyService->hasPasskeys($identity);
		$template->email = $identity?->getEmail();
		$template->resendInSeconds = $this->getResendInSeconds();
	}

	public function handleSendCode(): void
	{
		$identity = $this->getPendingIdentity();
		$isResend = $this->_passkeyService->getTwoFactorCodeSentAt() !== null;

		if ($this->getResendInSeconds() > 0) {
			$this->getPresenter()->flashMessageError('fcadmin.passkeys.errors.resendTooSoon');
			$this->getPresenter()->redirect('this');
		}

		try {
			$this->_mailer->sendTwoFactorCodeMail($identity, self::CODE_LIFETIME_MINUTES);
		} catch (TooManyTokenAttemptsException) {
			// Limit tokenů na IP nesmí skončit pětistovkou
			$this->getPresenter()->flashMessageError('fcadmin.passkeys.errors.tooManyCodeRequests');
			$this->getPresenter()->redirect('this');
		}

		$this->_passkeyService->markTwoFactorCodeSent();

		// Při prvním odeslání to uživateli řekne až následující obrazovka
		if ($isResend) {
			$this->getPresenter()->flashMessageSuccess('fcadmin.passkeys.twoFactor.codeSent');
		}

		$this->getPresenter()->redirect('this');
	}

	/** Zpět na výběr způsobu ověření; už odeslaný kód zůstává platný. */
	public function handleBack(): void
	{
		$this->getPendingIdentity();

		$this->_passkeyService->clearTwoFactorCodeSent();
		$this->getPresenter()->redirect('this');
	}

	/** Kolik sekund zbývá do dalšího odeslání; 0 = může se odeslat. */
	private function getResendInSeconds(): int
	{
		if (($sentAt = $this->_passkeyService->getTwoFactorCodeSentAt()) === null) {
			return 0;
		}

		return max(0, self::RESEND_DELAY_SECONDS - (time() - $sentAt));
	}

	public function validateForm(array $values, Form $form): void
	{
		$identity = $this->getPendingIdentity();

		// Role i aktivita se mezi zadáním hesla a kódu mohly změnit
		if (!$identity->getIsActive()) {
			$this->_passkeyService->clearPendingTwoFactor();
			$this->getPresenter()->flashMessageError('fcadmin.appGeneral.exceptions.inactiveUser');
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		// Odebraná role nebo nově přiřazené SSO — čekající stav je bezpředmětný
		if (!$this->_passkeyService->isPasskeyRequired($identity)) {
			$this->_passkeyService->clearPendingTwoFactor();
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		// Stejný ACL check jako AuthenticatorTrait::validateIdentity()
		if (
			!$identity->isAllowed($this->_fancyAdmin->getCustomerAclResource())
			&&
			!$identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource())
		) {
			$this->_passkeyService->clearPendingTwoFactor();
			$this->getPresenter()->flashMessageError('fcadmin.appGeneral.exceptions.noPermission');
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		// Kód je z abecedy bez malých písmen, velká uživateli nic nevezmou
		$token = $this->_onetimeTokenService->findToken(
			OnetimeTokenTypeEnum::LOGIN,
			mb_strtoupper(trim((string) $values['code'])),
			(string) $identity->getEmail(),
			markAsUsed: false,
		);

		if ($token === null || !$this->isTokenOwnedBy($token, $identity)) {
			if ($this->_passkeyService->increasePendingTwoFactorAttempts() >= self::MAX_CODE_ATTEMPTS) {
				$this->_passkeyService->clearPendingTwoFactor();
				$this->getPresenter()->flashMessageError('fcadmin.passkeys.errors.invalidCode');
				$this->getPresenter()->redirect(':Portal:Sign:in');
			}

			$form->addError('fcadmin.passkeys.errors.invalidCode');
			return;
		}

		$this->_identity = $identity;
		$this->_onetimeToken = $token;
	}

	public function processForm(): never
	{
		// usedAt doplní onLoggedIn hook v ADT\DoctrineAuthenticator\OTP\SecurityUser
		$this->_identity->setOnetimeToken($this->_onetimeToken);

		// Kód není klíč — session nesmí zdědit marker z dřívějšího přihlášení klíčem
		$this->_passkeyService->clearPasskeySession();
		$this->_passkeyService->markOtpSession();
		$this->_passkeyService->clearPendingTwoFactor();

		$this->_securityUser->login($this->_identity, context: $this->_fancyAdmin->getContext());

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}

	/** Bez čekajícího stavu (přímý přístup, expirace) uživatel začíná znovu od hesla. */
	private function getPendingIdentity(): Identity
	{
		if (($identity = $this->findPendingIdentity()) === null) {
			$this->_passkeyService->clearPendingTwoFactor();
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		return $identity;
	}

	private function findPendingIdentity(): ?Identity
	{
		$identityId = $this->_passkeyService->getPendingTwoFactorIdentityId();

		/** @var Identity|null $identity */
		$identity = $identityId === null
			? null
			: $this->_identityQueryFactory->create()->byId($identityId)->fetchOneOrNull();

		return $identity;
	}

	/** Přes instanceof, aby prošla i doctrine proxy. */
	private function isTokenOwnedBy(OnetimeToken $token, Identity $identity): bool
	{
		$objectClass = $token->getObjectClass();

		return $objectClass !== null
			&& $identity instanceof $objectClass
			&& $token->getObjectId() === $identity->getId();
	}
}
