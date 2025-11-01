<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Profile;

use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\UI\Components\Forms\IdentityProfileFormTrait;
use ADT\Forms\StaticContainer;
use Contributte\Translation\Exceptions\InvalidArgument;
use Exception;
use Nette\Application\UI\InvalidLinkException;

trait ProfileFormTrait
{
	use SecurityUserInject;
	use IdentityProfileFormTrait;
	use EntityManagerInject;
	use IdentityQueryFactoryInject;

	/**
	 * @throws Exception
	 */
	public function initForm(Form $form, ?Profile $profile): void
	{
		$form->addStaticContainer('identity', function(StaticContainer $container) {
			$this->addIdentityFields($container);
		});

		$this->addRoles($form, $this->getProfileRoles());

		$form->addSection(function () use ($form, $profile) {
			$form->mapToForm();
			$this->addProfileFields($form);
		}, name: 'account', watchForRedraw: isset($form['account']) ? [$form['account']] : []);

		$form->addSubmit('submit', 'app.forms.user.labels.submit');
	}

	/**
	 * @throws \DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws \Doctrine\DBAL\Exception|\ReflectionException
	 */
	public function processForm(Profile $profile, array $values): void
	{
		if ($profile->isNew()) {
			if (!$identity = $this->_identityQueryFactory->create()->byEmailOrPhoneNumber($values['email'], $values['phoneNumber'])->fetchOneOrNull()) {
				$this->_em->persist($identity);
				$identity->setSelectedAccount($profile->getAccount());
			}
			$profile->setIdentity($identity);
		} else {
			$identity = $profile->getIdentity();
		}

		if ($profile->getIsActive()) {
			$identity->setIsActive(true);
		}

		$this->processUserForm($identity);
	}

	protected function getEntityClass(): ?string
	{
		return Profile::class;
	}
}
