<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\Passkey\PasskeyException;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeySessionSection;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentityWithoutPasskeys;
use ADT\FancyAdmin\Tests\Fixtures\TestKeycloakManager;
use ADT\FancyAdmin\Tests\Fixtures\TestPasskey;
use ADT\FancyAdmin\Tests\Fixtures\TestPasskeyService;
use ADT\FancyAdmin\Tests\Fixtures\TestProfile;
use ADT\FancyAdmin\Tests\Fixtures\TestSession;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use Tester\Assert;

/**
 * PasskeyService - obal nad lbuchs/webauthn.
 *
 * Testuje se vsechno, co nepotrebuje autentikator ani databazi: odvozeni rpId, dekodovani
 * base64url, vynuceni 2FA, prace s challenge v session a oznaceni klicove session.
 * Overeni odpovedi autentikatoru (processCreate/processGet) testovat nejde - podepisuje
 * ji hardware.
 */

require __DIR__ . '/bootstrap.php';


function createService(array $config = [], ?TestSession $session = null, array $messages = []): TestPasskeyService
{
	return new TestPasskeyService(
		FancyAdminFactory::create($config + ['passkeyEnabled' => true]),
		new TestTranslator($messages),
		$session,
	);
}


test('rpId se odvodi z adresy administrace', function () {
	Assert::same('admin.example.com', PasskeyService::deriveRpId('admin.example.com'));
	Assert::same('admin.example.com', PasskeyService::deriveRpId('https://admin.example.com'));
	Assert::same('admin.example.com', PasskeyService::deriveRpId('http://admin.example.com'));
	// Cesta i port se zahazuji - rpId je jen domena.
	Assert::same('admin.example.com', PasskeyService::deriveRpId('https://admin.example.com/administrace'));
	Assert::same('admin.example.com', PasskeyService::deriveRpId('admin.example.com:8443'));
	Assert::same('admin.example.com', PasskeyService::deriveRpId('https://admin.example.com:8443/administrace'));
});


test('z cesty bez domeny se rpId odvodit neda', function () {
	// Projekt, ktery ma administraci na podadresari, musi rpId nastavit rucne -
	// FancyAdminExtension to hlida uz pri kompilaci kontejneru.
	Assert::same('', PasskeyService::deriveRpId('/administrace'));
	Assert::same('', PasskeyService::deriveRpId(''));
	Assert::same('', PasskeyService::deriveRpId(null));
});


test('rpId z konfigurace ma prednost', function () {
	Assert::same('admin.example.com', createService()->getRpId());
	Assert::same('example.com', createService(['passkeyRpId' => 'example.com'])->getRpId());
});


test('nazev Relying Party se bere z konfigurace nebo z nazvu projektu', function () {
	Assert::same('Test Project', createService()->getRpName());
	Assert::same('Muj admin', createService(['passkeyRpName' => 'Muj admin'])->getRpName());
});


test('base64url se dekoduje na binarku', function () {
	Assert::same('abc', PasskeyService::base64UrlDecode('YWJj'));
	// Znaky - a _ misto + a /
	Assert::same(base64_decode('++//'), PasskeyService::base64UrlDecode('--__'));
	// Vypustena vycpavka
	Assert::same('a', PasskeyService::base64UrlDecode('YQ'));
});


test('prazdny nebo vadny vstup se nedekoduje', function () {
	Assert::null(PasskeyService::base64UrlDecode(null));
	Assert::null(PasskeyService::base64UrlDecode(''));
	Assert::null(PasskeyService::base64UrlDecode('###'));
	Assert::null(PasskeyService::base64UrlDecode('YWJj!'));
});


test('dekodovani prezije kulaty prevod binarky', function () {
	$binary = random_bytes(64);
	$encoded = rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

	Assert::same($binary, PasskeyService::base64UrlDecode($encoded));
});


test('nazev klice se orezava a zkracuje na delku sloupce', function () {
	$service = createService();

	Assert::same('Telefon', $service->callNormalizeName('  Telefon  '));
	Assert::same(str_repeat('a', 64), $service->callNormalizeName(str_repeat('a', 100)));
	// Zkracuje se po znacich, ne po bajtech - diakritika se nerozsekne.
	Assert::same(str_repeat('ě', 64), $service->callNormalizeName(str_repeat('ě', 100)));
});


test('nazev klice je povinny', function () {
	$service = createService(messages: ['fcadmin.passkeys.form.errors.nameRequired' => 'Zadejte nazev']);

	Assert::exception(fn() => $service->callNormalizeName(''), PasskeyException::class, 'Zadejte nazev');
	Assert::exception(fn() => $service->callNormalizeName('   '), PasskeyException::class, 'Zadejte nazev');
});


test('pri vypnutych passkeys se sluzba odmitne pouzit', function () {
	// Server-side vynuceni opt-in configu - musi platit, i kdyby UI nekde zustalo viditelne.
	$service = createService(['passkeyEnabled' => false], messages: ['fcadmin.passkeys.errors.unavailable' => 'Nedostupne']);

	Assert::exception(fn() => $service->assertEnabled(), PasskeyException::class, 'Nedostupne');
	Assert::exception(fn() => $service->getLoginArgs(), PasskeyException::class, 'Nedostupne');
});


test('pri zapnutych passkeys sluzba projde', function () {
	Assert::noError(fn() => createService()->assertEnabled());
});


test('entita bez podpory klicu je chyba konfigurace, ne uzivatele', function () {
	$service = createService();

	Assert::exception(
		fn() => $service->callAssertHasPasskeys(new TestIdentityWithoutPasskeys()),
		RuntimeException::class,
		'%A%neimplementuje%A%',
	);
	Assert::noError(fn() => $service->callAssertHasPasskeys(new TestIdentity()));
});


test('chybejici passkey infrastruktura je taky chyba konfigurace', function () {
	$service = createService();

	Assert::exception($service->callGetPasskeyQueryFactory(...), RuntimeException::class, '%A%chybí implementace%A%');
});


test('identita bez klicu zadny klic nema', function () {
	$service = createService();
	$identity = new TestIdentity();

	Assert::false($service->hasPasskeys($identity));
	Assert::false($service->hasPasskeys(new TestIdentityWithoutPasskeys()));

	$identity->addPasskey(new TestPasskey($identity));
	Assert::true($service->hasPasskeys($identity));
});


test('2FA vyzaduje jen role s priznakem needs2fa', function () {
	$service = createService();
	$identity = new TestIdentity();

	Assert::false($service->isPasskeyRequired($identity));

	$identity->addRole(new TestAclRole('editor'));
	Assert::false($service->isPasskeyRequired($identity));

	$identity->addRole(new TestAclRole('admin')->setNeeds2fa(true));
	Assert::true($service->isPasskeyRequired($identity));
});


test('pri vypnutych passkeys je priznak needs2fa inertni', function () {
	$service = createService(['passkeyEnabled' => false]);
	$identity = new TestIdentity()->addRole(new TestAclRole('admin')->setNeeds2fa(true));

	Assert::false($service->isPasskeyRequired($identity));
});


test('2FA nejde obejit prepnutim uctu', function () {
	// Identity::getRoles() vraci jen role profilu vybraneho uctu, takze uzivatel s vice
	// profily by prepnutim uctu 2FA obesel - proto se berou role vsech profilu.
	$identity = new TestIdentity();
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka');
	new TestProfile($identity, $firma)->addRole(new TestAclRole('admin')->setNeeds2fa(true));
	new TestProfile($identity, $pobocka)->addRole(new TestAclRole('viewer'));

	$identity->setSelectedAccount($pobocka);

	// Vybrany ucet 2FA nevyzaduje, presto se klic vyzaduje.
	Assert::false(array_any($identity->getRoles(), fn($role) => $role->getNeeds2fa()));
	Assert::true(createService()->isPasskeyRequired($identity));
});


test('role se sbiraji z identity i ze vsech jejich profilu', function () {
	$service = createService();
	$identity = new TestIdentity();
	$vlastni = new TestAclRole('vlastni');
	$profilova = new TestAclRole('profilova');
	$account = new TestAccount('Firma');

	$identity->addRole($vlastni);
	new TestProfile($identity, $account)->addRole($profilova);

	Assert::same([$vlastni, $profilova], $service->callGetAllRoles($identity));
});


test('SSO ma prednost pred 2FA', function () {
	// Identitu s povinnym Keycloak loginem resi Keycloak, ne passkey.
	$fancyAdmin = FancyAdminFactory::create(['passkeyEnabled' => true, 'keycloakEnabled' => true]);
	$fancyAdmin->setKeycloakManager(new TestKeycloakManager(hasInstanceForIdentity: true));
	$service = new TestPasskeyService($fancyAdmin, new TestTranslator());

	$identity = new TestIdentity()->addRole(new TestAclRole('admin')->setNeeds2fa(true));

	Assert::false($service->isPasskeyRequired($identity));
});


test('bez prirazene SSO instance 2FA plati', function () {
	$fancyAdmin = FancyAdminFactory::create(['passkeyEnabled' => true, 'keycloakEnabled' => true]);
	$fancyAdmin->setKeycloakManager(new TestKeycloakManager(hasInstanceForIdentity: false));
	$service = new TestPasskeyService($fancyAdmin, new TestTranslator());

	$identity = new TestIdentity()->addRole(new TestAclRole('admin')->setNeeds2fa(true));

	Assert::true($service->isPasskeyRequired($identity));
});


test('zapnuty Keycloak bez manageru 2FA neshodi', function () {
	$fancyAdmin = FancyAdminFactory::create(['passkeyEnabled' => true, 'keycloakEnabled' => true]);
	$service = new TestPasskeyService($fancyAdmin, new TestTranslator());

	Assert::true($service->isPasskeyRequired(new TestIdentity()->addRole(new TestAclRole('admin')->setNeeds2fa(true))));
});


test('bootstrap okno: klic je vyzadovany, ale jeste zadny neni', function () {
	$service = createService();
	$identity = new TestIdentity()->addRole(new TestAclRole('admin')->setNeeds2fa(true));

	Assert::true($service->isEnrollmentPending($identity));

	$identity->addPasskey(new TestPasskey($identity));
	Assert::false($service->isEnrollmentPending($identity));
});


test('identita bez vynuceneho 2FA v bootstrap okne neni', function () {
	Assert::false(createService()->isEnrollmentPending(new TestIdentity()));
});


test('challenge je jednorazova', function () {
	$session = new TestSession();
	$service = createService(session: $session);

	$service->callStoreChallenge(PasskeySessionSection::CREATE_CHALLENGE, 'vyzva');

	Assert::same('vyzva', $service->callConsumeChallenge(PasskeySessionSection::CREATE_CHALLENGE));
	Assert::exception(
		fn() => $service->callConsumeChallenge(PasskeySessionSection::CREATE_CHALLENGE),
		PasskeyException::class,
	);
});


test('challenge se uklada s expiraci', function () {
	$session = new TestSession();
	$service = createService(session: $session);

	$service->callStoreChallenge(PasskeySessionSection::GET_CHALLENGE, 'vyzva');

	$expirations = $session->getSection(PasskeySessionSection::SECTION_NAME)->expirations;
	Assert::same(PasskeySessionSection::CHALLENGE_EXPIRATION, $expirations[PasskeySessionSection::GET_CHALLENGE]);
});


test('registrace a prihlaseni maji oddelene challenge', function () {
	// Soubezna registrace a prihlaseni si nesmi prepsat vyzvu.
	$service = createService(session: new TestSession());

	$service->callStoreChallenge(PasskeySessionSection::CREATE_CHALLENGE, 'registrace');
	$service->callStoreChallenge(PasskeySessionSection::GET_CHALLENGE, 'prihlaseni');

	Assert::same('registrace', $service->callConsumeChallenge(PasskeySessionSection::CREATE_CHALLENGE));
	Assert::same('prihlaseni', $service->callConsumeChallenge(PasskeySessionSection::GET_CHALLENGE));
});


test('chybejici challenge se hlasi prekladem', function () {
	$service = createService(session: new TestSession(), messages: ['fcadmin.passkeys.errors.expiredChallenge' => 'Vyzva vyprsela']);

	Assert::exception(
		fn() => $service->callConsumeChallenge(PasskeySessionSection::GET_CHALLENGE),
		PasskeyException::class,
		'Vyzva vyprsela',
	);
});


test('klicova session se oznaci a da zrusit', function () {
	$session = new TestSession();
	$service = createService(session: $session);

	Assert::false($service->isPasskeySession());

	$service->markPasskeySession();
	Assert::true($service->isPasskeySession());

	// Odhlaseni maze jen auth cookie, ne session - heslova session by si jinak vzala marker.
	$service->clearPasskeySession();
	Assert::false($service->isPasskeySession());
});


test('pri vypnutych passkeys se marker nemaze', function () {
	// clearPasskeySession() se vola pri prihlaseni heslem; s vypnutymi klici nema co delat.
	$session = new TestSession();
	$service = createService(session: $session);
	$service->markPasskeySession();

	$vypnuty = createService(['passkeyEnabled' => false], session: $session);
	$vypnuty->clearPasskeySession();

	Assert::true($service->isPasskeySession());
});


test('parametry prihlaseni jsou usernameless a s challenge v session', function () {
	$session = new TestSession();
	$service = createService(session: $session);

	$args = $service->getLoginArgs();

	Assert::type(stdClass::class, $args);
	Assert::same('admin.example.com', $args->publicKey->rpId);
	Assert::same('required', $args->publicKey->userVerification);
	// Zadny allowCredentials - prohlizec nabidne discoverable credentials.
	Assert::false(property_exists($args->publicKey, 'allowCredentials'));
	Assert::same(60000, $args->publicKey->timeout);

	// Challenge se ulozila do session pod klicem pro prihlaseni, zakodovana base64 - syrove
	// bajty by se v session handleru nemusely prenest v poradku.
	$stored = $session->getSection(PasskeySessionSection::SECTION_NAME)->get(PasskeySessionSection::GET_CHALLENGE);
	Assert::type('string', $stored);
	Assert::same(32, strlen(base64_decode($stored, true)));
});


test('parametry registrace nesou opaque user handle, ne id identity', function () {
	$session = new TestSession();
	$service = createService(session: $session);

	$identity = new TestIdentity();
	$identity->setId(15)->setEmail('jan@example.com')->setFirstName('Jan')->setLastName('Novak');
	$handle = random_bytes(32);
	$identity->setPasskeyUserHandle($handle);

	$args = $service->getRegistrationArgs($identity);

	Assert::same('admin.example.com', $args->publicKey->rp->id);
	Assert::same('Test Project', $args->publicKey->rp->name);
	Assert::same('jan@example.com', $args->publicKey->user->name);
	Assert::same('Jan Novak', $args->publicKey->user->displayName);
	// Autentikatoru jde nahodny handle, nikdy interni id identity.
	Assert::same($handle, $args->publicKey->user->id->getBinaryString());
	Assert::notSame('15', $args->publicKey->user->id->getBinaryString());
	Assert::same('required', $args->publicKey->authenticatorSelection->userVerification);
	Assert::true($args->publicKey->authenticatorSelection->requireResidentKey);
	Assert::same('required', $args->publicKey->authenticatorSelection->residentKey);
	// Attestation se nevyzaduje - server o autentikatoru nic vic vedet nepotrebuje.
	Assert::same('none', $args->publicKey->attestation);
	Assert::same(60000, $args->publicKey->timeout);
});


test('registrace vylouci klice, ktere identita uz ma', function () {
	$service = createService(session: new TestSession());
	$identity = new TestIdentity();
	$identity->setEmail('jan@example.com')->setPasskeyUserHandle(random_bytes(32));
	$identity->addPasskey(new TestPasskey($identity, 'Telefon', 'prvni-klic'));

	$args = $service->getRegistrationArgs($identity);

	Assert::count(1, $args->publicKey->excludeCredentials);
});


test('bez jmena se jako displayName pouzije e-mail', function () {
	$service = createService(session: new TestSession());
	$identity = new TestIdentity();
	$identity->setEmail('jan@example.com')->setPasskeyUserHandle(random_bytes(32));

	Assert::same('jan@example.com', $service->getRegistrationArgs($identity)->publicKey->user->displayName);
});
