<?php

namespace ADT\FancyAdmin\UI\Components\Forms\Profile;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\Form;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\UI\Components\Forms\IdentityProfileFormTrait;
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
		$this->addFormFields($form, $profile, isProfile: true);
	}

	/**
	 * @throws \DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException
	 * @throws \Doctrine\DBAL\Exception|\ReflectionException
	 */
	public function processForm(Profile $profile): void
	{
		$this->processUserForm($profile->getIdentity());
	}

	protected function getEntityClass(): ?string
	{
		return $this->_em->findEntityClassByInterface(Profile::class);
	}

	/**
	 * @param Profile $entity
	 * @param array $values
	 * @return void
	 */
	protected function initEntity(Entity $entity, array $values): void
	{
		if (
			!isset($values['identity']['email'])
			||
			(!$identity = $this->_identityQueryFactory->create()->disableSecurityFilter()->disableAccountFilter()->byEmail($values['identity']['email'])->fetchOneOrNull())
		) {
			$identity = new ($this->_em->findEntityClassByInterface(Identity::class));
			$identity->setContext($this->_fancyAdmin->getContext());
			$this->_em->persist($identity);
			$identity->setSelectedAccount($entity->getAccount());
		}
		$entity->setIdentity($identity);
	}

	public function isAllowedToEdit(): true
	{
		return true;
	}
}
