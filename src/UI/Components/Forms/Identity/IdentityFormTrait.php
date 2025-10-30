<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Identity;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\Forms\StaticContainer;
use App\Model\Entities\Identity;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\ArrayHash;
use ADT\Forms\Form;

/**
 * @property Identity $entity
 */
trait IdentityFormTrait
{
	use MailerInject;
	use AclRoleQueryFactoryInject;

	/**
	 * @throws Exception
	 */
	public function initForm(Form $form, ?Identity $identity): void
	{
		$this->initUserForm($form);

		$form->addText('bankAccount', 'fcadmin.forms.user.labels.bankAccount');

		$adminRoles = $this->_aclRoleQueryFactory->create()->byIsAdmin(true)->fetchPairs();
		$form->addDynamicContainer(
			'profiles',
			function (StaticContainer $container) use ($form, $adminRoles) {
				$container->addCheckbox('isActive', 'fcadmin.forms.user.labels.isActive');

				$container->addMultiSelect('roles', 'fcadmin.forms.user.labels.role', $adminRoles)
					->setRequired();
			}
		);

		$form->addSubmit('chosenCompany')
			->setValidationScope([])
			->onClick[] = function () {
			$this->redrawControl('chosenCompany');
		};
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function processForm(Identity $identity, ArrayHash $values): void
	{
		$this->processUserForm($identity);
	}

	public function initUserForm(Form $form): void
	{
		$form->addText('firstName', 'fcadmin.forms.user.labels.firstName')
			->setRequired();

		$form->addText('lastName', 'fcadmin.forms.user.labels.lastName')
			->setRequired();

		$form->addGroup('contactBlock');
		$form->addText('email', 'fcadmin.forms.user.labels.email');
		$form->addPhoneNumber('phoneNumber', 'fcadmin.forms.user.labels.phoneNumber', 'fcadmin.forms.user.errors.phoneNumber');
		$form->addGroup();

		$form->addSubmit('submit', 'fcadmin.forms.user.labels.submit');
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws \Doctrine\DBAL\Exception|Exception
	 */
	public function processUserForm(Identity $identity): void
	{
		try {
			$this->em->flush();
		} catch (UniqueConstraintViolationException $e) {
			$this->presenter->flashMessageError('app.forms.user.errors.credentialsConstrain');
			return;
		}

		if (!$identity->getPassword()) {
			$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_CREATION_VALID_FOR);
		}
	}

	protected function getEntityClass(): ?string
	{
		return Identity::class;
	}
}
