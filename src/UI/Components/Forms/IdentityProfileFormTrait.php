<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\Forms\Form;
use ADT\Forms\StaticContainer;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Forms\Container;

trait IdentityProfileFormTrait
{
	use FormTrait;
	use MailerInject;
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;
	use IsActiveFormField;

	/**
	 * @throws \Exception
	 */
	public function addIdentityFields(Form|StaticContainer $container, bool $required = true, bool $addIsActive = true): void
	{
		$container->addSection(function () use ($container, $required) {
			$container->addText('firstName', 'app.forms.user.labels.firstName')
				->setRequired($required);
			$container->addText('lastName', 'app.forms.user.labels.lastName')
				->setRequired($required);
		}, blockName: BlockNameEnum::ROW);

		if ($addIsActive) {
			$this->addIsActiveField($container);
		}

		$container->addSection(function () use ($container, $required) {
			$container->addEmail('email', 'app.forms.user.labels.email')
				->setRequired($required);

			$container->addPhoneNumber('phoneNumber', 'app.forms.user.labels.phoneNumber', 'app.forms.user.errors.phoneNumber')
				->setRequired($required);
		}, blockName: BlockNameEnum::ROW);
	}

	protected function addProfileFields(Form|Container $form): void
	{
	}

	/**
	 * @param AclRole[] $roles
	 */
	protected function addRoles(Container $container, array $roles): void
	{
		$rolePairs = [];
		foreach ($roles as $_aclRole) {
			$rolePairs[$_aclRole->getId()] = $_aclRole->getName();
		}
		$container->addMultiSelect('roles', 'app.forms.user.labels.role', $rolePairs)
			->setRequired();
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws Exception
	 */
	public function processUserForm(Identity $identity): void
	{
		try {
			$this->_em->flush();
		} catch (UniqueConstraintViolationException) {
			$this->getPresenter()->flashMessageError('app.forms.user.errors.credentialsConstrain');
			return;
		}

		if (!$identity->getPassword()) {
			$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_CREATION_VALID_FOR);
		}
	}

	protected function getIdentityRoles(): array
	{
		return  $this->_aclRoleQueryFactory->create()->byIsIdentity(true)->fetch();
	}

	/**
	 * @throws \Exception
	 */
	protected function getProfileRoles(): array
	{
		return $this->_aclRoleQueryFactory->create()->byIsProfile(true)->fetch();
	}
}
