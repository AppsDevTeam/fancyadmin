<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Identity;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\IdentityData;
use ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword\ResetPassword;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity\SignInAsIdentity;
use Exception;
use ReflectionException;

trait IdentityGridTrait
{
	use ResetPassword;
	use Editable;
	use SignInAsIdentity;
	use IdentityData;
	use MailerInject;
	use SecurityUserInject;
	use IdentityQueryFactoryInject;
	use EntityManagerInject;

	protected AclResourceNameEnum $aclResource = AclResourceNameEnum::BACKOFFICE_IDENTITIES;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addFilterText('search', '', ['firstName', 'lastName', 'email', 'phoneNumber']);
		$this->addIdentityData($grid);

		$grid->addAction('logout', 'Anonymizovat')
			->setRenderer(function (Identity $identity) {
				echo '<a href="' . $this->link('anonymize!', $identity->getId()) . '">
					<span class="fa fa-face-disguise"></span>
					Anonymizovat 
				</a>'; // TODO trnslate
			});
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return IdentityQueryFactory::class;
	}

	/**
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function handleAnonymize(int $identityId): void
	{
		/** @var Identity $identity */
		if (!$identity = $this->createQueryObject()->byId($identityId)->fetchOneOrNull()) {
			$this->error();
		}

		$identity->setLastName(mb_substr($identity->getLastName(), 0, 1) . '.');
		$identity->setEmail(null);
		$identity->setPhoneNumber(null);
		$identity->setIsActive(false);

		foreach ($identity->getProfiles() as $_profile) {
			$_profile->setIsActive(false);
		}

		$this->_em->flush();
	}
}
