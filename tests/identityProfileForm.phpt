<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Mailer\Mailer;
use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\UI\Components\Forms\IdentityProfileFormTrait;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;
use Nette\Forms\Container;
use Tester\Assert;

/**
 * Uložení identity/profilu v administraci - kdy odejde e-mail na nastavení hesla.
 *
 * SSO uživatel heslo nemá nikdy, e-mail proto dostat nesmí - jinak by mu chodil po každé
 * úpravě. Jakmile se mu ale SSO vypne, bez e-mailu by se do aplikace nedostal.
 */

require __DIR__ . '/bootstrap.php';


final class RecordingMailer implements Mailer
{
	/** @var list<Identity> */
	public array $passwordRecoveryMails = [];

	public function sendAccountCreationEmail(Identity $identity): void
	{
	}

	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime, bool $checkLimit = true): void
	{
		$this->passwordRecoveryMails[] = $identity;
	}

	public function sendTwoFactorCodeMail(Identity $identity, int $tokenLifetimeMinutes): void
	{
	}
}


/** Zachytí flash zprávy, které komponenta presenteru předá. */
final class FlashRecordingPresenter extends Presenter
{
	/** @var list<array{message: string, parameters: array}> */
	public array $successMessages = [];

	public function flashMessageSuccess(string $message, ?int $autoCloseDuration = null, array $parameters = []): stdClass
	{
		$this->successMessages[] = ['message' => $message, 'parameters' => $parameters];

		return new stdClass();
	}
}


class IdentityProfileFormHost
{
	use IdentityProfileFormTrait;

	public readonly FlashRecordingPresenter $presenter;

	public function __construct(TestEntityManager $em, Mailer $mailer, FancyAdmin $fancyAdmin)
	{
		$this->_em = $em;
		$this->_mailer = $mailer;
		$this->_fancyAdmin = $fancyAdmin;
		$this->presenter = new FlashRecordingPresenter();
	}

	public function getTemplate(): Template
	{
		throw new LogicException('Nepouziva se.');
	}

	public function getPresenter(): ?Presenter
	{
		return $this->presenter;
	}

	protected function addProfileFields(ADT\Forms\Form|Container $form, ?Profile $profile, array $roles): void
	{
	}

	protected function addIdentityFields(ADT\Forms\Form|Container $form, ?Identity $identity, array $roles): void
	{
	}

	public function isAllowedToEdit(?Identity $identity): bool
	{
		return true;
	}
}


/** Projekt s vlastním SSO mimo balíček - identitu pozná podle `ssoSub`. */
final class ExternalSsoFormHost extends IdentityProfileFormHost
{
	protected function getIdentityUsesSso(Identity $identity): bool
	{
		return $this->getIdentityUsesKeycloak($identity) || $identity->getSsoSub() !== null;
	}
}


/** Keycloak, ve kterém se identita přihlašuje jen tehdy, je-li v seznamu. */
function createKeycloakFancyAdmin(Identity ...$ssoIdentities): FancyAdmin
{
	$fancyAdmin = FancyAdminFactory::create(['keycloakEnabled' => true]);
	$fancyAdmin->setKeycloakManager(new class ($ssoIdentities) extends KeycloakManager {
		/** @param list<Identity> $ssoIdentities */
		public function __construct(private readonly array $ssoIdentities)
		{
		}

		public function getInstanceForIdentity(Identity $identity, bool $activeOnly = true): ?Keycloak
		{
			return in_array($identity, $this->ssoIdentities, true)
				? new ReflectionClass(Keycloak::class)->newInstanceWithoutConstructor()
				: null;
		}
	});

	return $fancyAdmin;
}


/**
 * @return array{0: TestEntityManager, 1: RecordingMailer}
 */
function createDependencies(): array
{
	return [new TestEntityManager([TestIdentity::class]), new RecordingMailer()];
}


test('identite bez hesla odejde e-mail na nastaveni hesla', function () {
	[$em, $mailer] = createDependencies();
	$identity = new TestIdentity()->setId(42)->setEmail('jan@example.com');
	$host = new IdentityProfileFormHost($em, $mailer, FancyAdminFactory::create());

	$host->processUserForm($identity);

	Assert::same(1, $em->flushCount);
	Assert::same([$identity], $mailer->passwordRecoveryMails);
	Assert::same('admin', $identity->getContext());
	// Admin musi vedet, ze uzivateli odesel e-mail.
	Assert::same(
		[['message' => 'fcadmin.forms.user.messages.passwordMailSent', 'parameters' => ['email' => 'jan@example.com']]],
		$host->presenter->successMessages,
	);
});


test('identita s heslem e-mail nedostane', function () {
	[$em, $mailer] = createDependencies();
	$identity = new TestIdentity()->setId(42)->setRawPassword('hash');

	new IdentityProfileFormHost($em, $mailer, FancyAdminFactory::create())->processUserForm($identity);

	Assert::same([], $mailer->passwordRecoveryMails);
});


test('uzivatel prihlasovany pres Keycloak e-mail nedostane', function () {
	[$em, $mailer] = createDependencies();
	$identity = new TestIdentity()->setId(42);

	$host = new IdentityProfileFormHost($em, $mailer, createKeycloakFancyAdmin($identity));

	$host->processUserForm($identity);

	Assert::same(1, $em->flushCount);
	Assert::same([], $mailer->passwordRecoveryMails);
	Assert::same([], $host->presenter->successMessages);
});


test('po vypnuti SSO e-mail odejde i existujici identite', function () {
	// Deaktivovana instance nebo odebrana vazba na SSO - manager identitu nevrati.
	[$em, $mailer] = createDependencies();
	$identity = new TestIdentity()->setId(42)->setSsoSub('keycloak-sub');

	new IdentityProfileFormHost($em, $mailer, createKeycloakFancyAdmin())->processUserForm($identity);

	Assert::same([$identity], $mailer->passwordRecoveryMails);
});


test('vypnuty Keycloak se na SSO identitu nepta', function () {
	[$em, $mailer] = createDependencies();
	$identity = new TestIdentity()->setId(42);
	$fancyAdmin = createKeycloakFancyAdmin($identity);
	new ReflectionProperty($fancyAdmin, 'keycloakEnabled')->setValue($fancyAdmin, false);

	new IdentityProfileFormHost($em, $mailer, $fancyAdmin)->processUserForm($identity);

	Assert::same([$identity], $mailer->passwordRecoveryMails);
});


test('projekt s vlastnim SSO pozna svou identitu prepsanim metody', function () {
	[$em, $mailer] = createDependencies();
	$ssoIdentity = new TestIdentity()->setId(42)->setSsoSub('TMCZ_9001234567');
	$passwordIdentity = new TestIdentity()->setId(43);
	$host = new ExternalSsoFormHost($em, $mailer, FancyAdminFactory::create());

	$host->processUserForm($ssoIdentity);
	$host->processUserForm($passwordIdentity);

	Assert::same([$passwordIdentity], $mailer->passwordRecoveryMails);
});
