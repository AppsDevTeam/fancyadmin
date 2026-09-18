<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestProfile;
use ADT\FancyAdmin\Tests\Fixtures\TestSso;
use Nette\Security\Passwords;
use Tester\Assert;

/**
 * IdentityTrait - prihlaseny uzivatel.
 *
 * Nejvic zalezi na getRoles()/isAllowed(): role se skladaji z vlastnich roli identity
 * a roli profilu vybraneho uctu, takze prepnuti uctu meni opravneni.
 */

require __DIR__ . '/bootstrap.php';


test('cele jmeno se sklada ze jmena a prijmeni', function () {
	$identity = new TestIdentity();

	Assert::same('', $identity->getFullName());

	Assert::same('Jan', $identity->setFirstName('Jan')->getFullName());
	Assert::same('Jan Novak', $identity->setLastName('Novak')->getFullName());
	Assert::same('Novak', $identity->setFirstName(null)->getFullName());
});


test('bez jmena se pouzije prihlasovaci jmeno', function () {
	$identity = new TestIdentity();
	$identity->setUsername('jnovak');

	Assert::same('jnovak', $identity->getFullName());

	// Jakmile jmeno je, prihlasovaci jmeno ustoupi.
	Assert::same('Jan', $identity->setFirstName('Jan')->getFullName());
});


test('prazdne jmeno neni mezera', function () {
	$identity = new TestIdentity();
	$identity->setFirstName('  ')->setLastName('  ');

	Assert::same('', $identity->getFullName());
});


test('heslo se uklada zahashovane', function () {
	$identity = new TestIdentity();

	$identity->setPassword('TajneHeslo123');

	Assert::notSame('TajneHeslo123', $identity->getPassword());
	Assert::true(new Passwords()->verify('TajneHeslo123', $identity->getPassword()));
	Assert::false(new Passwords()->verify('jine', $identity->getPassword()));
});


test('prazdne heslo puvodni heslo nepremaze', function () {
	// Formulare posilaji prazdne pole, kdyz uzivatel heslo nemeni.
	$identity = new TestIdentity();
	$identity->setPassword('TajneHeslo123');
	$hash = $identity->getPassword();

	$identity->setPassword(null);
	Assert::same($hash, $identity->getPassword());

	$identity->setPassword('');
	Assert::same($hash, $identity->getPassword());
});


test('kazde zahashovani da jiny otisk', function () {
	$a = new TestIdentity()->setPassword('TajneHeslo123');
	$b = new TestIdentity()->setPassword('TajneHeslo123');

	Assert::notSame($a->getPassword(), $b->getPassword());
});


test('identita se autentizatoru predstavuje retezcovym id', function () {
	$identity = new TestIdentity();
	$identity->setId(15);

	Assert::same('15', $identity->getAuthObjectId());

	$identity->setAuthToken('token-abc');
	Assert::same('token-abc', $identity->getAuthToken());

	// Metadata balicek nepouziva, ale rozhrani je vyzaduje.
	Assert::same([], $identity->getAuthMetadata());
	$identity->setAuthMetadata(['a' => 1]);
	Assert::same([], $identity->getAuthMetadata());
});


test('identita je sama sobe identitou', function () {
	$identity = new TestIdentity();

	Assert::same($identity, $identity->getIdentity());
});


test('gravatar se pocita z e-mailu', function () {
	$identity = new TestIdentity();
	$identity->setEmail('  Jan.Novak@Example.COM ');

	$expected = 'https://www.gravatar.com/avatar/' . hash('sha256', 'jan.novak@example.com') . '?s=90&d=mp';
	Assert::same($expected, $identity->getGravatar());
	Assert::contains('d=identicon', $identity->getGravatar('identicon'));
});


test('role admina povoluje vse', function () {
	$identity = new TestIdentity();
	$identity->addRole(new TestAclRole('admin', isAdmin: true));

	Assert::true($identity->isAdmin());
	Assert::true($identity->isAllowed(new StringResource('cokoliv')));
	Assert::true($identity->isAllowed(AclResourceNameEnum::FULL_DATA));
});


test('bezna role povoluje jen sve zdroje', function () {
	$identity = new TestIdentity();
	$identity->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalBackoffice.orders')));

	Assert::false($identity->isAdmin());
	Assert::true($identity->isAllowed(new StringResource('portalBackoffice.orders')));
	Assert::false($identity->isAllowed(new StringResource('portalBackoffice.identities')));
});


test('vypnuta vazba role na zdroj opravneni nedava', function () {
	$identity = new TestIdentity();
	$identity->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalBackoffice.orders'), isActive: false));

	Assert::false($identity->isAllowed(new StringResource('portalBackoffice.orders')));
});


test('identita bez roli nema zadne opravneni', function () {
	$identity = new TestIdentity();

	Assert::false($identity->isAdmin());
	Assert::false($identity->isAllowed(new StringResource('cokoliv')));
});


test('stejna role se neprida dvakrat', function () {
	$identity = new TestIdentity();
	$role = new TestAclRole('editor');

	$identity->addRole($role)->addRole($role);

	Assert::count(1, $identity->getRoles());
});


test('profil se pripoji obema smery a jen jednou', function () {
	$identity = new TestIdentity();
	$account = new TestAccount('Firma');
	$profile = new TestProfile(account: $account);

	$identity->addProfile($profile);
	$identity->addProfile($profile);

	Assert::count(1, $identity->getProfiles());
	Assert::same($identity, $profile->getIdentity());
});


test('profil se vybira podle zvoleneho uctu', function () {
	$identity = new TestIdentity();
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka');
	$profileFirma = new TestProfile($identity, $firma);
	$profilePobocka = new TestProfile($identity, $pobocka);

	// Bez zvoleneho uctu neni profil.
	Assert::null($identity->getProfile());

	$identity->setSelectedAccount($pobocka);
	Assert::same($profilePobocka, $identity->getProfile());

	$identity->setSelectedAccount($firma);
	Assert::same($profileFirma, $identity->getProfile());
});


test('u podrizeneho uctu se sahne po profilu rodice', function () {
	// Uzivatel ma profil jen na materske firme, ale pracuje pod jeji pobockou.
	$identity = new TestIdentity();
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka', parent: $firma);
	$profileFirma = new TestProfile($identity, $firma);

	$identity->setSelectedAccount($pobocka);

	Assert::same($profileFirma, $identity->getProfile());
});


test('bez profilu na uctu ani jeho rodici se vrati null', function () {
	$identity = new TestIdentity();
	$cizi = new TestAccount('Cizi');
	new TestProfile($identity, new TestAccount('Firma'));

	$identity->setSelectedAccount($cizi);

	Assert::null($identity->getProfile());
});


test('role se skladaji z vlastnich a z profilu zvoleneho uctu', function () {
	$identity = new TestIdentity();
	$vlastni = new TestAclRole('vlastni');
	$profilova = new TestAclRole('profilova');
	$account = new TestAccount('Firma');
	$profile = new TestProfile($identity, $account);

	$identity->addRole($vlastni);
	$profile->addRole($profilova);

	// Dokud neni ucet zvoleny, profilova role se nepocita.
	Assert::same([$vlastni], $identity->getRoles());

	$identity->setSelectedAccount($account);
	Assert::same([$vlastni, $profilova], $identity->getRoles());
});


test('opravneni z profilu plati jen na zvolenem uctu', function () {
	$identity = new TestIdentity();
	$account = new TestAccount('Firma');
	$profile = new TestProfile($identity, $account);
	$profile->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalCustomer.orders')));

	Assert::false($identity->isAllowed(new StringResource('portalCustomer.orders')));

	$identity->setSelectedAccount($account);
	Assert::true($identity->isAllowed(new StringResource('portalCustomer.orders')));
});


test('ucty se berou z profilu', function () {
	$identity = new TestIdentity();
	$firma = new TestAccount('Firma');
	$jina = new TestAccount('Jina');
	new TestProfile($identity, $firma);
	new TestProfile($identity, $jina);

	Assert::same([$firma, $jina], $identity->getAccounts());
});


test('podrizene ucty se sesbiraji ze vsech uctu', function () {
	$identity = new TestIdentity();
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka');
	$sklad = new TestAccount('Sklad');
	$firma->addSubaccount($pobocka)->addSubaccount($sklad);
	new TestProfile($identity, $firma);

	Assert::same([$pobocka, $sklad], $identity->getSubaccounts());
});


test('zvoleny ucet, SSO a kontext jsou nepovinne', function () {
	$identity = new TestIdentity();
	$account = new TestAccount('Firma');
	$sso = new TestSso();

	Assert::null($identity->getSelectedAccount());
	Assert::null($identity->getSso());
	Assert::null($identity->getContext());
	Assert::null($identity->getSsoSub());

	$identity->setSelectedAccount($account)->setSso($sso)->setContext('admin')->setSsoSub('sub-123');

	Assert::same($account, $identity->getSelectedAccount());
	Assert::same($sso, $identity->getSso());
	Assert::same('admin', $identity->getContext());
	Assert::same('sub-123', $identity->getSsoSub());

	Assert::null($identity->setSelectedAccount(null)->getSelectedAccount());
	Assert::null($identity->setSso(null)->getSso());
});


test('kontaktni udaje', function () {
	$identity = new TestIdentity();

	Assert::null($identity->getEmail());
	Assert::null($identity->getPhoneNumber());
	Assert::null($identity->getUsername());

	$identity->setEmail('jan@example.com')->setPhoneNumber('+420123456789')->setUsername('jnovak');

	Assert::same('jan@example.com', $identity->getEmail());
	Assert::same('+420123456789', $identity->getPhoneNumber());
	Assert::same('jnovak', $identity->getUsername());
});


test('anonymizace se zaznamena i s autorem', function () {
	$identity = new TestIdentity();
	$admin = new TestIdentity();
	$at = new DateTimeImmutable('2026-06-01 10:00:00');

	Assert::null($identity->getAnonymizedAt());
	Assert::null($identity->getAnonymizedBy());

	$identity->setAnonymizedAt($at)->setAnonymizedBy($admin);

	Assert::same($at, $identity->getAnonymizedAt());
	Assert::same($admin, $identity->getAnonymizedBy());
});


test('jednorazovy token je nepovinny', function () {
	$identity = new TestIdentity();

	Assert::null($identity->getOnetimeToken());
	Assert::same($identity, $identity->setOnetimeToken(null));
});


test('POZOR: retezcovy zdroj projde jen u admina', function () {
	// Identity::isAllowed() prijima string|Resource, ale AclRole::isAllowed() uz jen Resource.
	// U admina se diky zkracenemu vyhodnoceni k roli nedojde, u bezne role to spadne.
	$admin = new TestIdentity()->addRole(new TestAclRole('admin', isAdmin: true));
	Assert::true($admin->isAllowed('cokoliv'));

	$editor = new TestIdentity()->addRole(new TestAclRole('editor'));
	Assert::exception(fn() => $editor->isAllowed('cokoliv'), TypeError::class);
});
