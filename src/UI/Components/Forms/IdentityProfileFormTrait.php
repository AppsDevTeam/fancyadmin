<?php

namespace ADT\FancyAdmin\UI\Components\Forms;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\DI\Injects\ProfileQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\Forms\DynamicContainer;
use ADT\Forms\Form;
use ADT\Forms\Section;
use ADT\Forms\StaticContainer;
use Contributte\Translation\Exceptions\InvalidArgument;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Forms\Container;
use Nette\Forms\SubmitterControl;

trait IdentityProfileFormTrait
{
	use FormTrait;
	use MailerInject;
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;
	use IdentityQueryFactoryInject;
	use IsActiveFormField;
	use ProfileQueryFactoryInject;

	abstract protected function addProfileFields(Form|Container $form, ?Profile $profile, array $roles): void;
	abstract protected function addIdentityFields(Form|Container $form, ?Identity $identity, array $roles): void;
	abstract protected function getContext(): ?string;

	/**
	 * @throws \Exception
	 */
	public function addFormFields(Form $form, Identity|Profile|null $entity, bool $isProfile): void
	{
		$isEdit = (bool) $entity;

		$form->addSection(function (Section $section) use ($form, $isProfile, $isEdit, $entity) {
			$primaryEl = $form;

			if ($isProfile) {
				$primaryEl = $form->addStaticContainer('identity', function(StaticContainer $container) use ($isEdit) {
					$this->addBasicIdentityFields($container, $isEdit);
				});
			} else {
				$this->addBasicIdentityFields($form, $isEdit);
			}

			if ($isEdit || $primaryEl['email']->getValue()) {
				$this->addIsActiveField($form);
				if (!$isEdit) {
					$form['isActive']->setValue(true);
				}

				if (!$isProfile) {
					$this->addRoles($form, $this->getIdentityRoles($this->getContext()), required: !$this->getProfileRoles($this->getContext()));

					$form->addDynamicContainer(
						'profiles',
						function (StaticContainer $container) use ($form, $entity) {
							static $i = 0;
							$container->addCheckbox('isActive', 'app.forms.user.labels.isActive');
							$this->addRoles($container, $this->getProfileRoles($this->getContext()), required: true);
							$container->addSelect('account', 'app.forms.user.labels.company', $this->_accountQueryFactory->create()->disableAccountFilter()->fetchPairs('fullName'))
								->setPrompt('---');
							$container->addSection(function () use ($form, $container, $entity, $i) {
								$form->mapToForm();
								$this->addProfileFields($container, $entity?->getProfiles()[$i] ?? null, $container['roles']->getValue());
							}, 'account', watchForRedraw: [$container['account'], $container['roles']]);
							$i++;
						}
					);

					$form->mapToForm();

					$roleControls = [$form['roles']];
					if (!$form->isSubmitted()) {
						$roleControls[] = $form['profiles'][DynamicContainer::NEW_PREFIX]['roles'];
					}
					foreach ($form['profiles']->getContainers() as $_profileContainer) {
						$roleControls[] = $_profileContainer['roles'];
					}
					$form->addSection(function () use ($form, $entity) {
						$roleIds = $form['roles']->getValue();
						foreach ($form['profiles']->getContainers() as $_profileContainer) {
							$roleIds = array_merge($roleIds, $_profileContainer['roles']->getValue());
						}
						$roles = $this->_aclRoleQueryFactory->create()->byId($roleIds)->fetch();
						$this->addIdentityFields($form, $entity instanceof Profile ? $entity->getIdentity() : $entity, $roles);
					}, name: 'roleBasedFields', watchForRedraw: $roleControls);
				} else {
					$this->addRoles($form, $this->getProfileRoles($this->getContext()), required: true);
					$watchForRedraw = [$form['roles']];
					if (isset($form['account'])) {
						$watchForRedraw[] = $form['account'];
					}
					$form->addSection(function () use ($form, $entity) {
						$form->mapToForm();
						$this->addProfileFields($form, $entity, $form['roles']->getValue());
					}, name: 'profileFields', watchForRedraw: $watchForRedraw);
				}

				$form->addSubmit('submit', 'app.forms.user.labels.submit'); // TODO translate
			}
		});
	}

	protected function addBasicIdentityFields(Container $container, bool $isEdit): void
	{
		$container->addEmail('email', 'app.forms.user.labels.email')
			->setRequired(true);

		$container->addSubmit('search', 'Vyhledat')
			->setValidationScope([$container['email']])
			->onClick[] = function () use ($container) {
				$this->redrawControl($this->getName());
			};

		if (!$isEdit && $this->_identityQueryFactory->create()->byEmail($container['email']->getValue())->fetchOneOrNull()) {
			$container['email']->addError('Uživatel již ve vybraném účtu existuje.'); // TODO translate
		}

		if ($isEdit || $container['email']->getValue()) {
			$container['email']->setHtmlAttribute('readonly');
			$container['search']->setHtmlAttribute('hidden');
			if (isset($container->getForm()['account'])) {
				$container->getForm()['account']->setHtmlAttribute('readonly');
			}

			$identity = $this->_identityQueryFactory->create()->disableSecurityFilter()->disableAccountFilter()->byEmail($container['email']->getValue())->fetchOneOrNull();

			if ($this->isAllowedToEdit($identity)) {
				$container->addSection(function () use ($container) {
					$container->addText('firstName', 'app.forms.user.labels.firstName')
						->setRequired();
					$container->addText('lastName', 'app.forms.user.labels.lastName')
						->setRequired();
				}, blockName: BlockNameEnum::ROW);

				$container->addPhoneNumber('phoneNumber', 'app.forms.user.labels.phoneNumber', 'app.forms.user.errors.phoneNumber')
					->setRequired();

				if ($identity && $container->getForm()->isSubmitted() instanceof SubmitterControl && $container->getForm()->isSubmitted()->getName() === 'search') {
					$container['firstName']->setValue($identity->getFirstName());
					$container['lastName']->setValue($identity->getLastName());
					$container['phoneNumber']->setValue($identity->getPhoneNumber());
				}
			}
		}
	}

	/**
	 * @param AclRole[] $roles
	 */
	protected function addRoles(Container $container, array $roles, bool $required): void
	{
		$rolePairs = [];
		foreach ($roles as $_aclRole) {
			$rolePairs[$_aclRole->getId()] = $_aclRole->getName();
		}
		$container->addMultiSelect('roles', 'app.forms.user.labels.role', $rolePairs)
			->setRequired($required);
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
			$identity->setContext($this->getContext());
			$this->_em->flush();
		} catch (UniqueConstraintViolationException) {
			$this->getPresenter()->flashMessageError('app.forms.user.errors.credentialsConstrain');
			return;
		}

		if (!$identity->getPassword()) {
			$this->_mailer->sendPasswordRecoveryMail($identity, OnetimeToken::PASSWORD_CREATION_VALID_FOR);
		}
	}

	protected function getIdentityRoles(?string $context): array
	{
		return  $this->_aclRoleQueryFactory->create()->byType(AclRoleTypeEnum::IDENTITY)->byContext($context)->fetch();
	}

	/**
	 * @throws \Exception
	 */
	protected function getProfileRoles(?string $context): array
	{
		return $this->_aclRoleQueryFactory->create()->byType(AclRoleTypeEnum::PROFILE)->byContext($context)->fetch();
	}
}
